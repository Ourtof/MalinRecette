<?php

namespace App\Controller\Api;

use App\Constants\UserConstants;
use App\Controller\Api\Traits\JsonRequestTrait;
use App\Entity\User;
use App\Service\EmailValidatorService;
use App\Service\PasswordValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends AbstractController
{
    use JsonRequestTrait;

    #[Route('/api/register', name: 'api_register', methods: ['POST', 'OPTIONS'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        PasswordValidatorService $passwordValidator,
        EmailValidatorService $emailValidator,
        ?RateLimiterFactory $registerLimiter = null
    ): Response {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        // rate limiting
        if ($registerLimiter !== null) {
            try {
                $limiter = $registerLimiter->create($request->getClientIp());
                if (!$limiter->consume()->isAccepted()) {
                    return new JsonResponse([
                        'error' => 'Trop de tentatives. Réessaie plus tard.'
                    ], 429);
                }
            } catch (\Exception $e) {
                // si le rate limiter pas dispo, on continue sans limitation
            }
        }

        $data = $this->getJsonData($request);
        if ($data === null) {
            return $this->jsonInvalidResponse();
        }

        // vérification des champs
        $requiredFields = ['email', 'password', 'pseudo', 'prenom', 'nom', 'adresse', 'ville', 'codePostal'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => "Le champ '$field' est manquant"], Response::HTTP_BAD_REQUEST);
            }
        }

        // validation de l'email
        $emailValidation = $emailValidator->validateAndNormalize($data['email']);
        if (!$emailValidation['valid']) {
            return new JsonResponse(['error' => $emailValidation['error']], Response::HTTP_BAD_REQUEST);
        }
        $email = $emailValidation['email'];

        // validation du mot de passe
        $passwordErrors = $passwordValidator->validate($data['password']);
        if (!empty($passwordErrors)) {
            return new JsonResponse([
                'error' => 'Mot de passe invalide',
                'details' => $passwordErrors
            ], Response::HTTP_BAD_REQUEST);
        }

        // validation des longueurs de champs
        foreach (UserConstants::MAX_LENGTHS as $field => $maxLength) {
            if (isset($data[$field]) && strlen($data[$field]) > $maxLength) {
                return new JsonResponse([
                    'error' => "Le champ '$field' ne peut pas dépasser $maxLength caractères"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // vérifie si un utilisateur existe déjà avec cet email
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Un utilisateur avec cet email existe déjà'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPseudo($data['pseudo']);
        $user->setPrenom($data['prenom']);
        $user->setNom($data['nom']);
        $user->setAdresse($data['adresse']);
        $user->setVille($data['ville']);
        $user->setCodePostal((int) $data['codePostal']);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Utilisateur enregistré avec succès'], Response::HTTP_CREATED);
    }
}
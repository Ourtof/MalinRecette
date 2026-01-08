<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class RegisterController extends AbstractController
{
    private const MAX_LENGTHS = [
        'pseudo' => 50,
        'prenom' => 50,
        'nom' => 50,
        'adresse' => 255,
        'ville' => 50,
    ];

    #[Route('/api/register', name: 'api_register', methods: ['POST', 'OPTIONS'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        // rate limiting
        try {
            /** @var RateLimiterFactory $registerLimiter */
            $registerLimiter = $this->container->get('limiter.register');
            $limiter = $registerLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                return new JsonResponse([
                    'error' => 'Trop de tentatives. Réessaie plus tard.'
                ], 429);
            }
        } catch (\Exception $e) {
            // si le rate limiter pas dispo, on continue sans limitation
            // (pour éviter de casser l'inscription)
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        // vérification des champs
        $requiredFields = ['email', 'password', 'pseudo', 'prenom', 'nom', 'adresse', 'ville', 'codePostal'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => "Le champ '$field' est manquant"], Response::HTTP_BAD_REQUEST);
            }
        }

        // validation de l'email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }
        $email = strtolower(trim($data['email']));
        if (strlen($email) > 180) {
            return new JsonResponse(['error' => 'Email trop long'], Response::HTTP_BAD_REQUEST);
        }

        // validation du mot de passe
        $passwordErrors = $this->validatePassword($data['password']);
        if (!empty($passwordErrors)) {
            return new JsonResponse([
                'error' => 'Mot de passe invalide',
                'details' => $passwordErrors
            ], Response::HTTP_BAD_REQUEST);
        }

        // validation des longueurs de champs
        foreach (self::MAX_LENGTHS as $field => $maxLength) {
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

    /**
     * @return array<string>
     */
    private function validatePassword(string $password): array
    {
        $errors = [];

        // complexité du mdp
        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une minuscule';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial';
        }
        
        return $errors;
    }
}
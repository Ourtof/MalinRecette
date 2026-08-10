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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class UserController extends AbstractController
{
    use JsonRequestTrait;

    #[Route('/user', name: 'api_user_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/user', name: 'api_user_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateMe(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        PasswordValidatorService $passwordValidator,
        EmailValidatorService $emailValidator
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $data = $this->getJsonData($request);
        if ($data === null) {
            return $this->jsonInvalidResponse();
        }

        if (array_key_exists('prenom', $data)) {
            $prenom = trim($data['prenom']);
            if (strlen($prenom) > UserConstants::MAX_LENGTHS['prenom']) {
                return $this->json(['error' => "Le prénom ne peut pas dépasser " . UserConstants::MAX_LENGTHS['prenom'] . " caractères"], 400);
            }
            $user->setPrenom($prenom);
        }
        if (array_key_exists('nom', $data)) {
            $nom = trim($data['nom']);
            if (strlen($nom) > UserConstants::MAX_LENGTHS['nom']) {
                return $this->json(['error' => "Le nom ne peut pas dépasser " . UserConstants::MAX_LENGTHS['nom'] . " caractères"], 400);
            }
            $user->setNom($nom);
        }
        if (array_key_exists('pseudo', $data)) {
            $pseudo = trim($data['pseudo']);
            if (strlen($pseudo) > UserConstants::MAX_LENGTHS['pseudo']) {
                return $this->json(['error' => "Le pseudo ne peut pas dépasser " . UserConstants::MAX_LENGTHS['pseudo'] . " caractères"], 400);
            }
            $user->setPseudo($pseudo);
        }
        if (array_key_exists('email', $data)) {
            $emailValidation = $emailValidator->validateAndNormalize($data['email']);
            if (!$emailValidation['valid']) {
                return $this->json(['error' => $emailValidation['error']], 400);
            }
            $email = $emailValidation['email'];
            // vérifie que l'email est pas déjà utilisé par un autre utilisateur
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json(['error' => 'Cet email est déjà utilisé'], 409);
            }
            $user->setEmail($email);
        }
        if (array_key_exists('adresse', $data)) {
            $adresse = trim($data['adresse']);
            if (strlen($adresse) > UserConstants::MAX_LENGTHS['adresse']) {
                return $this->json(['error' => "L'adresse ne peut pas dépasser " . UserConstants::MAX_LENGTHS['adresse'] . " caractères"], 400);
            }
            $user->setAdresse($adresse);
        }
        if (array_key_exists('ville', $data)) {
            $ville = trim($data['ville']);
            if (strlen($ville) > UserConstants::MAX_LENGTHS['ville']) {
                return $this->json(['error' => "La ville ne peut pas dépasser " . UserConstants::MAX_LENGTHS['ville'] . " caractères"], 400);
            }
            $user->setVille($ville);
        }
        if (array_key_exists('codePostal', $data)) {
            if (!preg_match('/^\d{5}$/', $data['codePostal'])) {
                return $this->json(['error' => 'Code postal invalide (5 chiffres requis)'], 400);
            }
            $user->setCodePostal($data['codePostal']);
        }
        if (array_key_exists('password', $data) && !empty($data['password'])) {
            $passwordErrors = $passwordValidator->validate($data['password']);
            if (!empty($passwordErrors)) {
                return $this->json([
                    'error' => 'Mot de passe invalide',
                    'details' => $passwordErrors
                ], 400);
            }
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $data['password']
            );
            $user->setPassword($hashedPassword);
        }

        $em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/user', name: 'api_user_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteMe(EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        // La suppression en cascade est configurée dans l'entité User
        // pour UserFoodProfile (cascade: ['persist', 'remove'])
        // et pour les recettes (cascade: ['remove'])
        $em->remove($user);
        $em->flush();

        return $this->json(['message' => 'Profil supprimé avec succès'], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        $data = [
            'id'         => $user->getId(),
            'email'      => $user->getUserIdentifier(),
            'prenom'     => $user->getPrenom(),
            'nom'        => $user->getNom(),
            'pseudo'     => $user->getPseudo(),
        ];
        
        // données sensibles uniquement pour les admins
        if ($this->isGranted('ROLE_ADMIN')) {
            $data['roles'] = $user->getRoles();
            $data['adresse'] = $user->getAdresse();
            $data['ville'] = $user->getVille();
            $data['codePostal'] = (string) $user->getCodePostal();
        } else {
            // pour l'utilisateur lui-même, on affiche quand même ses données
            $data['adresse'] = $user->getAdresse();
            $data['ville'] = $user->getVille();
            $data['codePostal'] = (string) $user->getCodePostal();
        }
        
        return $data;
    }
}

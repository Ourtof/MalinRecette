<?php

namespace App\Controller\Api;

use App\Entity\User;
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
    private const MAX_LENGTHS = [
        'pseudo' => 50,
        'prenom' => 50,
        'nom' => 50,
        'adresse' => 255,
        'ville' => 50,
    ];

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
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'JSON invalide'], 400);
        }

        if (array_key_exists('prenom', $data)) {
            $prenom = trim($data['prenom']);
            if (strlen($prenom) > self::MAX_LENGTHS['prenom']) {
                return $this->json(['error' => "Le prénom ne peut pas dépasser " . self::MAX_LENGTHS['prenom'] . " caractères"], 400);
            }
            $user->setPrenom($prenom);
        }
        if (array_key_exists('nom', $data)) {
            $nom = trim($data['nom']);
            if (strlen($nom) > self::MAX_LENGTHS['nom']) {
                return $this->json(['error' => "Le nom ne peut pas dépasser " . self::MAX_LENGTHS['nom'] . " caractères"], 400);
            }
            $user->setNom($nom);
        }
        if (array_key_exists('pseudo', $data)) {
            $pseudo = trim($data['pseudo']);
            if (strlen($pseudo) > self::MAX_LENGTHS['pseudo']) {
                return $this->json(['error' => "Le pseudo ne peut pas dépasser " . self::MAX_LENGTHS['pseudo'] . " caractères"], 400);
            }
            $user->setPseudo($pseudo);
        }
        if (array_key_exists('email', $data)) {
            $email = strtolower(trim($data['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => 'Email invalide'], 400);
            }
            if (strlen($email) > 180) {
                return $this->json(['error' => 'Email trop long'], 400);
            }
            // vérifie que l'email est pas déjà utilisé par un autre utilisateur
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json(['error' => 'Cet email est déjà utilisé'], 409);
            }
            $user->setEmail($email);
        }
        if (array_key_exists('adresse', $data)) {
            $adresse = trim($data['adresse']);
            if (strlen($adresse) > self::MAX_LENGTHS['adresse']) {
                return $this->json(['error' => "L'adresse ne peut pas dépasser " . self::MAX_LENGTHS['adresse'] . " caractères"], 400);
            }
            $user->setAdresse($adresse);
        }
        if (array_key_exists('ville', $data)) {
            $ville = trim($data['ville']);
            if (strlen($ville) > self::MAX_LENGTHS['ville']) {
                return $this->json(['error' => "La ville ne peut pas dépasser " . self::MAX_LENGTHS['ville'] . " caractères"], 400);
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
            $passwordErrors = $this->validatePassword($data['password']);
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

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id'         => $user->getId(),
            'email'      => $user->getUserIdentifier(),
            'roles'      => $user->getRoles(),
            'prenom'     => $user->getPrenom(),
            'nom'        => $user->getNom(),
            'pseudo'     => $user->getPseudo(),
            'adresse'    => $user->getAdresse(),
            'ville'      => $user->getVille(),
            'codePostal' => (string) $user->getCodePostal(),
        ];
    }

    /**
     * @return array<string> Liste des erreurs (vide si valide)
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

<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class RegisterController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST', 'OPTIONS'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // Préflight CORS éventuel
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        // Vérification des champs obligatoires
        $requiredFields = ['email', 'password', 'pseudo', 'prenom', 'nom', 'adresse', 'ville', 'codePostal'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => "Le champ '$field' est manquant"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Vérifie si un utilisateur existe déjà avec cet email
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Un utilisateur avec cet email existe déjà'], Response::HTTP_CONFLICT);
        }

        // Création du nouvel utilisateur
        $user = new User();
        $user->setEmail($data['email']);
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
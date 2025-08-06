<?php

namespace App\Controller;

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
   #[Route('/api/register', name: 'api_register', methods: ['POST'])]
public function register(
    Request $request,
    UserPasswordHasherInterface $passwordHasher,
    EntityManagerInterface $entityManager
): Response {
    // 1. On récupère les données envoyées en JSON
    $data = json_decode($request->getContent(), true);

    if (!$data) {
        return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
    }

    // 2. On vérifie la présence des champs obligatoires
    $requiredFields = ['email', 'password', 'pseudo', 'prenom', 'nom', 'date_naissance', 'adresse', 'ville', 'code_postal', 'adresse_mail'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return new JsonResponse(['error' => "Le champ '$field' est manquant"], Response::HTTP_BAD_REQUEST);
        }
    }

    // 3. On instancie un nouvel utilisateur
    $user = new User();
    $user->setEmail($data['email']);
    $user->setPseudo($data['pseudo']);
    $user->setPrenom($data['prenom']);
    $user->setNom($data['nom']);
    $user->setAdresse($data['adresse']);
    $user->setVille($data['ville']);
    $user->setCodePostal((int) $data['code_postal']);
    $user->setDateNaissance(new \DateTime($data['date_naissance'])); // Format ISO attendu

    // 4. Hash du mot de passe
    $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
    $user->setPassword($hashedPassword);

    // 5. Sauvegarde en base
    $entityManager->persist($user);
    $entityManager->flush();

    // 6. Réponse
    return new JsonResponse(['message' => 'Utilisateur enregistré avec succès'], Response::HTTP_CREATED);
}
}
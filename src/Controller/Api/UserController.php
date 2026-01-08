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
            $user->setPrenom($data['prenom']);
        }
        if (array_key_exists('nom', $data)) {
            $user->setNom($data['nom']);
        }
        if (array_key_exists('pseudo', $data)) {
            $user->setPseudo($data['pseudo']);
        }
        if (array_key_exists('email', $data)) {
            $user->setEmail($data['email']);
        }
        if (array_key_exists('adresse', $data)) {
            $user->setAdresse($data['adresse']);
        }
        if (array_key_exists('ville', $data)) {
            $user->setVille($data['ville']);
        }
        if (array_key_exists('codePostal', $data)) {
            if (!preg_match('/^\d{5}$/', $data['codePostal'])) {
                return $this->json(['error' => 'Code postal invalide (5 chiffres requis)'], 400);
            }
            $user->setCodePostal($data['codePostal']);
        }
        if (array_key_exists('password', $data) && !empty($data['password'])) {
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
}

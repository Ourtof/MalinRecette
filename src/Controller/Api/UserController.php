<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/user', name: 'api_user_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateMe(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if ($data === null) {
            return $this->json(['error' => 'JSON invalide'], 400);
        }

        // On met à jour uniquement les champs envoyés
        if (array_key_exists('prenom', $data)) {
            $user->setPrenom($data['prenom']);
        }
        if (array_key_exists('nom', $data)) {
            $user->setNom($data['nom']);
        }
        if (array_key_exists('pseudo', $data)) {
            $user->setPseudo($data['pseudo']);
        }

        $em->flush();

        return $this->json($this->serializeUser($user));
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'     => $user->getId(),
            'email'  => $user->getUserIdentifier(),
            'roles'  => $user->getRoles(),
            'prenom' => $user->getPrenom(),
            'nom'    => $user->getNom(),
            'pseudo' => $user->getPseudo(),
        ];
    }
}

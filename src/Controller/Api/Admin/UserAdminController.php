<?php

namespace App\Controller\Api\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class UserAdminController extends AbstractController
{
    #[Route('/user', name: 'api_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();

        $data = array_map(
            fn (User $user) => [
                'id'      => $user->getId(),
                'email'   => $user->getEmail(),
                'pseudo'  => $user->getPseudo(),
                'roles'   => $user->getRoles(),
                'enabled' => $user->isEnabled(),
            ],
            $users
        );

        return $this->json($data);
    }

    #[Route('/user/{id}/toggle-enabled', name: 'api_admin_users_toggle_enabled', methods: ['PATCH'])]
    public function toggleEnabled(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User|null $targetUser */
        $targetUser = $userRepository->find($id);

        if (!$targetUser) {
            return $this->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($currentUser && $currentUser->getId() === $targetUser->getId()) {
            return $this->json(
                ['message' => 'Tu ne peux pas désactiver ton propre compte.'],
                400
            );
        }

        $targetUser->setEnabled(!$targetUser->isEnabled());
        $em->flush();

        return $this->json([
            'id'      => $targetUser->getId(),
            'email'   => $targetUser->getEmail(),
            'pseudo'  => $targetUser->getPseudo(),
            'roles'   => $targetUser->getRoles(),
            'enabled' => $targetUser->isEnabled(),
        ]);
    }
}

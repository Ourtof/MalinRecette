<?php

namespace App\Controller\Api\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class UserAdminController extends AbstractController
{
    #[Route('/user', name: 'api_admin_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): JsonResponse
    {
        // récup paramètre de requête
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 20);
        $limit = max(1, min(100, $limit));
        $search = trim((string) $request->query->get('search', ''));
        $status = $request->query->get('status'); // 'active', 'inactive'

        // base du QueryBuilder
        $qb = $userRepository->createQueryBuilder('u');

        // filtre recherche
        if ($search !== '') {
            $qb
                ->andWhere('u.email LIKE :search OR u.pseudo LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        // filtre statut
        if ($status === 'active') {
            $qb
                ->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', true);
        } elseif ($status === 'inactive') {
            $qb
                ->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', false);
        }

        // total avant pagination
        $qbCount = clone $qb;
        $total = (int) $qbCount
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // pagination + sélection des champs
        $qb
            ->select('u.id, u.email, u.pseudo, u.roles, u.enabled')
            ->orderBy('u.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getArrayResult();

        // format de réponse standardisé
        return $this->json([
            'items' => $items,
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
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

        if ($currentUser->getId() === $targetUser->getId()) {
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

    #[Route('/user/{id}/profile', name: 'api_admin_user_profile', methods: ['GET'])]
    public function getUserProfile(
        int $id,
        UserRepository $userRepository
    ): JsonResponse {
        /** @var User|null $targetUser */
        $targetUser = $userRepository->find($id);

        if (!$targetUser) {
            return $this->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        $profile = $this->serializeUser($targetUser);
        $foodProfile = $targetUser->getFoodProfile();

        $profile['foodProfile'] = $foodProfile ? [
            'goalType'       => $foodProfile->getGoalType(),
            'dietType'       => $foodProfile->getDietType(),
            'isHalal'        => $foodProfile->isHalal(),
            'allergies'      => $foodProfile->getAllergies(),
            'autreAllergies' => $foodProfile->getAutreAllergies(),
        ] : [
            'goalType'       => 'CLASSIQUE',
            'dietType'       => 'CLASSIQUE',
            'isHalal'        => false,
            'allergies'      => [],
            'autreAllergies' => null,
        ];

        return $this->json($profile);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id'         => $user->getId(),
            'email'      => $user->getUserIdentifier(),
            'prenom'     => $user->getPrenom(),
            'nom'        => $user->getNom(),
            'pseudo'     => $user->getPseudo(),
            'roles'      => $user->getRoles(),
            'adresse'    => $user->getAdresse(),
            'ville'      => $user->getVille(),
            'codePostal' => (string) $user->getCodePostal(),
            'enabled'    => $user->isEnabled(),
        ];
    }
}

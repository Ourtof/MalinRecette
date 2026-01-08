<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class LoginController extends AbstractController
{
    #[Route('/login', name: 'api_login', methods: ['POST', 'OPTIONS'])]
    public function login(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $JWTManager
    ): JsonResponse {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        // rate limiting
        try {
            /** @var RateLimiterFactory $loginLimiter */
            $loginLimiter = $this->container->get('limiter.login');
            $limiter = $loginLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                return $this->json([
                    'message' => 'Trop de tentatives. Réessaie dans quelques minutes.'
                ], 429);
            }
        } catch (\Exception $e) {
            // si le rate limiter pas dispo, on continue sans limitation
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['message' => 'Email et mot de passe requis.'], 400);
        }

        /** @var User|null $user */
        $user = $entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['message' => 'Identifiants invalides.'], 401);
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Identifiants invalides.'], 401);
        }

        if (!$user->isEnabled()) {
            return $this->json(
                ['message' => 'Compte désactivé. Contacte un administrateur.'],
                403
            );
        }

        // génération du token
        $token = $JWTManager->create($user);

        return $this->json([
            'token' => $token,
            'user' => [
                'id'    => $user->getId(),
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}

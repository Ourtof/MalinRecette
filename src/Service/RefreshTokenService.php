<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenService
{
    private const TOKEN_LENGTH = 64;
    private const EXPIRATION_DAYS = 30;

    public function __construct(
        private EntityManagerInterface $em,
        private RefreshTokenRepository $refreshTokenRepo
    ) {
    }

    /**
     * Génère un nouveau refresh token pour un utilisateur
     */
    public function generateRefreshToken(User $user): RefreshToken
    {
        // révoque les anciens tokens (rotation)
        $this->refreshTokenRepo->revokeAllForUser($user);

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $expiresAt = new \DateTimeImmutable('+' . self::EXPIRATION_DAYS . ' days');

        $refreshToken = new RefreshToken();
        $refreshToken
            ->setUser($user)
            ->setToken($token)
            ->setExpiresAt($expiresAt);

        $this->em->persist($refreshToken);
        $this->em->flush();

        return $refreshToken;
    }

    /**
     * Valide un refresh token et retourne l'utilisateur associé
     */
    public function validateRefreshToken(string $token): ?User
    {
        $refreshToken = $this->refreshTokenRepo->findValidToken($token);
        
        if (!$refreshToken) {
            return null;
        }

        return $refreshToken->getUser();
    }

    /**
     * Révoque un refresh token
     */
    public function revokeToken(string $token): void
    {
        $this->refreshTokenRepo->revokeToken($token);
    }

    /**
     * Révoque tous les tokens d'un utilisateur
     */
    public function revokeAllForUser(User $user): void
    {
        $this->refreshTokenRepo->revokeAllForUser($user);
    }
}

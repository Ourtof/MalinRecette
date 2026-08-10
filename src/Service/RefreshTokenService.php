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
    private const HASH_ALGORITHM = 'sha256';

    public function __construct(
        private EntityManagerInterface $em,
        private RefreshTokenRepository $refreshTokenRepo
    ) {
    }

    /**
     * Hashe un token
     */
    private function hashToken(string $token): string
    {
        return hash(self::HASH_ALGORITHM, $token);
    }

    /**
     * Génère un nouveau refresh token pour un utilisateur
     * Retourne le token en clair et l'entité avec le hash stocké
     */
    public function generateRefreshToken(User $user): RefreshToken
    {
        // révoque les anciens tokens (rotation)
        $this->refreshTokenRepo->revokeAllForUser($user);

        $tokenPlain = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $tokenHash = $this->hashToken($tokenPlain);
        $expiresAt = new \DateTimeImmutable('+' . self::EXPIRATION_DAYS . ' days');

        $refreshToken = new RefreshToken();
        $refreshToken
            ->setUser($user)
            ->setToken($tokenHash)
            ->setExpiresAt($expiresAt);

        $this->em->persist($refreshToken);
        $this->em->flush();

        // stock tempo le token en clair
        $refreshToken->setPlainToken($tokenPlain);

        return $refreshToken;
    }

    /**
     * Valide un refresh token et retourne l'utilisateur associé
     */
    public function validateRefreshToken(string $token): ?User
    {
        $tokenHash = $this->hashToken($token);
        $refreshToken = $this->refreshTokenRepo->findValidToken($tokenHash);
        
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
        $tokenHash = $this->hashToken($token);
        $this->refreshTokenRepo->revokeToken($tokenHash);
    }

    /**
     * Révoque tous les tokens d'un utilisateur
     */
    public function revokeAllForUser(User $user): void
    {
        $this->refreshTokenRepo->revokeAllForUser($user);
    }
}

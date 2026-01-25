<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidToken(string $token): ?RefreshToken
    {
        $refreshToken = $this->findOneBy(['token' => $token]);
        
        if (!$refreshToken || $refreshToken->isExpired()) {
            return null;
        }
        
        return $refreshToken;
    }

    public function revokeAllForUser(User $user): void
    {
        $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function revokeToken(string $token): void
    {
        $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->execute();
    }

    public function cleanupExpired(): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}

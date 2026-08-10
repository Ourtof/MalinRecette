<?php

namespace App\Tests\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class RefreshTokenServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
private RefreshTokenRepository&MockObject $refreshTokenRepo;
    private RefreshTokenService $refreshTokenService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->refreshTokenRepo = $this->createMock(RefreshTokenRepository::class);
        $this->refreshTokenService = new RefreshTokenService($this->em, $this->refreshTokenRepo);
    }

    public function testGenerateRefreshTokenRevoqueLesAnciensTokensEtPersisteLeNouveau(): void
    {
        $user = $this->createMock(User::class);

        $this->refreshTokenRepo->expects($this->once())
            ->method('revokeAllForUser')
            ->with($user);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (RefreshToken $token) use ($user): bool {
                return $token->getUser() === $user
                    && strlen($token->getToken()) === 64
                    && $token->getExpiresAt() > new \DateTimeImmutable();
            }));

        $this->em->expects($this->once())->method('flush');

        $refreshToken = $this->refreshTokenService->generateRefreshToken($user);

        $this->assertSame($user, $refreshToken->getUser());
        $this->assertNotNull($refreshToken->getPlainToken());
        $this->assertSame(128, strlen($refreshToken->getPlainToken()));
        $this->assertSame(hash('sha256', $refreshToken->getPlainToken()), $refreshToken->getToken());
    }

    public function testValidateRefreshTokenRetourneUtilisateurSiTokenValide(): void
    {
        $user = $this->createMock(User::class);
        $tokenPlain = 'mon-refresh-token';
        $tokenHash = hash('sha256', $tokenPlain);

        $refreshToken = new RefreshToken();
        $refreshToken
            ->setUser($user)
            ->setToken($tokenHash)
            ->setExpiresAt(new \DateTimeImmutable('+1 day'));

        $this->refreshTokenRepo->expects($this->once())
            ->method('findValidToken')
            ->with($tokenHash)
            ->willReturn($refreshToken);

        $result = $this->refreshTokenService->validateRefreshToken($tokenPlain);

        $this->assertSame($user, $result);
    }

    public function testValidateRefreshTokenRetourneNullSiTokenInvalide(): void
    {
        $this->refreshTokenRepo->expects($this->once())
            ->method('findValidToken')
            ->willReturn(null);

        $result = $this->refreshTokenService->validateRefreshToken('token-invalide');

        $this->assertNull($result);
    }

    public function testRevokeTokenAppelleLeRepositoryAvecLeHash(): void
    {
        $tokenPlain = 'mon-refresh-token';
        $tokenHash = hash('sha256', $tokenPlain);

        $this->refreshTokenRepo->expects($this->once())
            ->method('revokeToken')
            ->with($tokenHash);

        $this->refreshTokenService->revokeToken($tokenPlain);
    }

    public function testRevokeAllForUserAppelleLeRepository(): void
    {
        $user = $this->createMock(User::class);

        $this->refreshTokenRepo->expects($this->once())
            ->method('revokeAllForUser')
            ->with($user);

        $this->refreshTokenService->revokeAllForUser($user);
    }
}

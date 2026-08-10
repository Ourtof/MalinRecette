<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\RefreshTokenController;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Service\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;

class RefreshTokenControllerTest extends AbstractApiControllerTestCase
{
    private RefreshTokenController $controller;
    private RefreshTokenService&MockObject $refreshTokenService;
    private JWTTokenManagerInterface&MockObject $jwtManager;

    protected function setUp(): void
    {
        $this->controller = new RefreshTokenController();
        $this->configureControllerContainer($this->controller);

        $this->refreshTokenService = $this->createMock(RefreshTokenService::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
    }

    public function testOptionsRetourne204(): void
    {
        $request = Request::create('/api/refresh', 'OPTIONS');

        $response = $this->controller->refresh(
            $request,
            $this->refreshTokenService,
            $this->jwtManager
        );

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testJsonInvalideRetourne400(): void
    {
        $request = Request::create('/api/refresh', 'POST', [], [], [], [], 'invalide');

        $response = $this->controller->refresh(
            $request,
            $this->refreshTokenService,
            $this->jwtManager
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('JSON invalide', $this->decodeJsonResponse($response)['error']);
    }

    public function testRefreshTokenManquantRetourne400(): void
    {
        $request = $this->createJsonRequest('POST', []);

        $response = $this->controller->refresh(
            $request,
            $this->refreshTokenService,
            $this->jwtManager
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('refreshToken requis', $this->decodeJsonResponse($response)['error']);
    }

    public function testRefreshTokenInvalideRetourne401(): void
    {
        $this->refreshTokenService->method('validateRefreshToken')->willReturn(null);

        $request = $this->createJsonRequest('POST', ['refreshToken' => 'token-invalide']);

        $response = $this->controller->refresh(
            $request,
            $this->refreshTokenService,
            $this->jwtManager
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCompteDesactiveRetourne403(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isEnabled')->willReturn(false);

        $this->refreshTokenService->method('validateRefreshToken')->willReturn($user);

        $request = $this->createJsonRequest('POST', ['refreshToken' => 'token-valide']);

        $response = $this->controller->refresh(
            $request,
            $this->refreshTokenService,
            $this->jwtManager
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testRefreshReussiRetourneNouveauxTokens(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isEnabled')->willReturn(true);

        $this->refreshTokenService->method('validateRefreshToken')->willReturn($user);
        $this->jwtManager->method('create')->willReturn('nouveau-jwt');

        $newRefreshToken = new RefreshToken();
        $newRefreshToken->setPlainToken('nouveau-refresh');
        $this->refreshTokenService->method('generateRefreshToken')->willReturn($newRefreshToken);

        $request = $this->createJsonRequest('POST', ['refreshToken' => 'token-valide']);

        $response = $this->controller->refresh(
            $request,
            $this->refreshTokenService,
            $this->jwtManager
        );

        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('nouveau-jwt', $data['token']);
        $this->assertSame('nouveau-refresh', $data['refreshToken']);
    }
}

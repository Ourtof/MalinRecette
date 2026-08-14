<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\LoginController;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginControllerTest extends AbstractApiControllerTestCase
{
    private LoginController $controller;
    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private RefreshTokenService&MockObject $refreshTokenService;

    protected function setUp(): void
    {
        $this->controller = new LoginController();
        $this->configureControllerContainer($this->controller);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->refreshTokenService = $this->createMock(RefreshTokenService::class);
    }

    public function testOptionsRetourne204(): void
    {
        $request = Request::create('/api/login', 'OPTIONS');

        $response = $this->controller->login(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testChampsManquantsRetourne400(): void
    {
        $request = $this->createJsonRequest('POST', ['email' => 'test@example.com']);

        $response = $this->controller->login(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Email et mot de passe requis.', $this->decodeJsonResponse($response)['message']);
    }

    public function testIdentifiantsInvalidesRetourne401(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);

        $request = $this->createJsonRequest('POST', [
            'email' => 'inconnu@example.com',
            'password' => 'MotDePasse1!',
        ]);

        $response = $this->controller->login(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Identifiants invalides.', $this->decodeJsonResponse($response)['message']);
    }

    public function testMotDePasseIncorrectRetourne401(): void
    {
        $user = $this->createMock(User::class);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $this->entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $request = $this->createJsonRequest('POST', [
            'email' => 'user@example.com',
            'password' => 'MauvaisMotDePasse1!',
        ]);

        $response = $this->controller->login(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCompteDesactiveRetourne403(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isEnabled')->willReturn(false);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $this->entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $request = $this->createJsonRequest('POST', [
            'email' => 'user@example.com',
            'password' => 'MotDePasse1!',
        ]);

        $response = $this->controller->login(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testConnexionReussieRetourneToken(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isEnabled')->willReturn(true);
        $user->method('getId')->willReturn(1);
        $user->method('getUserIdentifier')->willReturn('user@example.com');
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $this->entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->jwtManager->method('create')->willReturn('jwt-token');

        $refreshToken = new RefreshToken();
        $refreshToken->setPlainToken('refresh-token-plain');
        $this->refreshTokenService->method('generateRefreshToken')->willReturn($refreshToken);

        $request = $this->createJsonRequest('POST', [
            'email' => 'user@example.com',
            'password' => 'MotDePasse1!',
        ]);

        $response = $this->controller->login(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('jwt-token', $data['token']);
        $this->assertSame('refresh-token-plain', $data['refreshToken']);
        $this->assertSame(1, $data['user']['id']);
    }
}

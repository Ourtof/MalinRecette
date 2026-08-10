<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\RegisterController;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailValidatorService;
use App\Service\PasswordValidatorService;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegisterControllerTest extends AbstractApiControllerTestCase
{
    private RegisterController $controller;
    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private PasswordValidatorService&MockObject $passwordValidator;
    private EmailValidatorService&MockObject $emailValidator;
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private RefreshTokenService&MockObject $refreshTokenService;

    protected function setUp(): void
    {
        $this->controller = new RegisterController();
        $this->configureControllerContainer($this->controller);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->passwordValidator = $this->createMock(PasswordValidatorService::class);
        $this->emailValidator = $this->createMock(EmailValidatorService::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->refreshTokenService = $this->createMock(RefreshTokenService::class);
    }

    public function testOptionsRetourne204(): void
    {
        $request = Request::create('/api/register', 'OPTIONS');

        $response = $this->controller->register(
            $request,
            $this->passwordHasher,
            $this->entityManager,
            $this->passwordValidator,
            $this->emailValidator,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testJsonInvalideRetourne400(): void
    {
        $request = Request::create('/api/register', 'POST', [], [], [], [], 'pas-du-json');

        $response = $this->controller->register(
            $request,
            $this->passwordHasher,
            $this->entityManager,
            $this->passwordValidator,
            $this->emailValidator,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('JSON invalide', $this->decodeJsonResponse($response)['error']);
    }

    public function testChampManquantRetourne400(): void
    {
        $request = $this->createJsonRequest('POST', [
            'email' => 'user@example.com',
            'password' => 'MotDePasse1!',
        ]);

        $response = $this->controller->register(
            $request,
            $this->passwordHasher,
            $this->entityManager,
            $this->passwordValidator,
            $this->emailValidator,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('est manquant', $this->decodeJsonResponse($response)['error']);
    }

    public function testEmailInvalideRetourne400(): void
    {
        $this->emailValidator->method('validateAndNormalize')->willReturn([
            'valid' => false,
            'error' => 'Email invalide',
        ]);

        $request = $this->createJsonRequest('POST', $this->donneesInscriptionValides());

        $response = $this->controller->register(
            $request,
            $this->passwordHasher,
            $this->entityManager,
            $this->passwordValidator,
            $this->emailValidator,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Email invalide', $this->decodeJsonResponse($response)['error']);
    }

    public function testMotDePasseInvalideRetourne400(): void
    {
        $this->emailValidator->method('validateAndNormalize')->willReturn([
            'valid' => true,
            'email' => 'user@example.com',
        ]);
        $this->passwordValidator->method('validate')->willReturn(['Le mot de passe est trop faible']);

        $request = $this->createJsonRequest('POST', $this->donneesInscriptionValides());

        $response = $this->controller->register(
            $request,
            $this->passwordHasher,
            $this->entityManager,
            $this->passwordValidator,
            $this->emailValidator,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Mot de passe invalide', $this->decodeJsonResponse($response)['error']);
    }

    public function testEmailDejaUtiliseRetourne409(): void
    {
        $this->emailValidator->method('validateAndNormalize')->willReturn([
            'valid' => true,
            'email' => 'user@example.com',
        ]);
        $this->passwordValidator->method('validate')->willReturn([]);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(new User());
        $this->entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);

        $request = $this->createJsonRequest('POST', $this->donneesInscriptionValides());

        $response = $this->controller->register(
            $request,
            $this->passwordHasher,
            $this->entityManager,
            $this->passwordValidator,
            $this->emailValidator,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testInscriptionReussieRetourne201(): void
    {
        $this->emailValidator->method('validateAndNormalize')->willReturn([
            'valid' => true,
            'email' => 'user@example.com',
        ]);
        $this->passwordValidator->method('validate')->willReturn([]);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');
        $this->jwtManager->method('create')->willReturn('jwt-token');

        $refreshToken = new RefreshToken();
        $refreshToken->setToken('refresh-hash');
        $this->refreshTokenService->method('generateRefreshToken')->willReturn($refreshToken);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $request = $this->createJsonRequest('POST', $this->donneesInscriptionValides());

        $response = $this->controller->register(
            $request,
            $this->passwordHasher,
            $this->entityManager,
            $this->passwordValidator,
            $this->emailValidator,
            $this->jwtManager,
            $this->refreshTokenService
        );

        $data = $this->decodeJsonResponse($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('jwt-token', $data['token']);
        $this->assertSame('user@example.com', $data['user']['email']);
    }

    /**
     * @return array<string, string|int>
     */
    private function donneesInscriptionValides(): array
    {
        return [
            'email' => 'user@example.com',
            'password' => 'MotDePasse1!',
            'pseudo' => 'pseudo',
            'prenom' => 'Jean',
            'nom' => 'Dupont',
            'adresse' => '1 rue Test',
            'ville' => 'Paris',
            'codePostal' => '75001',
        ];
    }
}

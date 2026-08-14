<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\UserController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailValidatorService;
use App\Service\PasswordValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserControllerTest extends AbstractApiControllerTestCase
{
    private UserController $controller;
    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private PasswordValidatorService&MockObject $passwordValidator;
    private EmailValidatorService&MockObject $emailValidator;

    protected function setUp(): void
    {
        $this->controller = new UserController();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->passwordValidator = $this->createMock(PasswordValidatorService::class);
        $this->emailValidator = $this->createMock(EmailValidatorService::class);
    }

    public function testMeRetourneUtilisateurConnecte(): void
    {
        $user = $this->creerUtilisateur();
        $this->configureControllerContainer($this->controller, $user);

        $response = $this->controller->me();
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $data['id']);
        $this->assertSame('user@example.com', $data['email']);
        $this->assertSame('Jean', $data['prenom']);
    }

    public function testMeSansUtilisateurRetourne401(): void
    {
        $this->configureControllerContainer($this->controller, null);

        $response = $this->controller->me();

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testUpdateMeJsonInvalideRetourne400(): void
    {
        $user = $this->creerUtilisateur();
        $this->configureControllerContainer($this->controller, $user);

        $request = \Symfony\Component\HttpFoundation\Request::create('/api/user', 'PUT', [], [], [], [], 'invalide');

        $response = $this->controller->updateMe(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->passwordValidator,
            $this->emailValidator
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUpdateMeCodePostalInvalideRetourne400(): void
    {
        $user = $this->creerUtilisateur();
        $this->configureControllerContainer($this->controller, $user);

        $request = $this->createJsonRequest('PUT', ['codePostal' => '123']);

        $response = $this->controller->updateMe(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->passwordValidator,
            $this->emailValidator
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Code postal invalide (5 chiffres requis)', $this->decodeJsonResponse($response)['error']);
    }

    public function testUpdateMeEmailDejaUtiliseRetourne409(): void
    {
        $user = $this->creerUtilisateur();
        $this->configureControllerContainer($this->controller, $user);

        $this->emailValidator->method('validateAndNormalize')->willReturn([
            'valid' => true,
            'email' => 'autre@example.com',
        ]);

        $autreUtilisateur = $this->createMock(User::class);
        $autreUtilisateur->method('getId')->willReturn(99);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($autreUtilisateur);
        $this->entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);

        $request = $this->createJsonRequest('PUT', ['email' => 'autre@example.com']);

        $response = $this->controller->updateMe(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->passwordValidator,
            $this->emailValidator
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testUpdateMeReussi(): void
    {
        $user = $this->creerUtilisateur();
        $this->configureControllerContainer($this->controller, $user);

        $this->entityManager->expects($this->once())->method('flush');

        $request = $this->createJsonRequest('PUT', ['prenom' => 'Pierre']);

        $response = $this->controller->updateMe(
            $request,
            $this->entityManager,
            $this->passwordHasher,
            $this->passwordValidator,
            $this->emailValidator
        );

        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Pierre', $data['prenom']);
    }

    public function testDeleteMeReussi(): void
    {
        $user = $this->creerUtilisateur();
        $this->configureControllerContainer($this->controller, $user);

        $this->entityManager->expects($this->once())->method('remove')->with($user);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->deleteMe($this->entityManager);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Profil supprimé avec succès', $this->decodeJsonResponse($response)['message']);
    }

    private function creerUtilisateur(): User
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPseudo('pseudo');
        $user->setPrenom('Jean');
        $user->setNom('Dupont');
        $user->setAdresse('1 rue Test');
        $user->setVille('Paris');
        $user->setCodePostal(75001);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('hashed');

        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 1);

        return $user;
    }
}

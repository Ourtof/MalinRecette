<?php

namespace App\Tests\Controller\Api\Admin;

use App\Controller\Api\Admin\UserAdminController;
use App\Entity\User;
use App\Entity\UserFoodProfile;
use App\Repository\UserRepository;
use App\Tests\Controller\Api\AbstractApiControllerTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class UserAdminControllerTest extends AbstractApiControllerTestCase
{
    private UserAdminController $controller;
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->controller = new UserAdminController();
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testToggleEnabledUtilisateurIntrouvableRetourne404(): void
    {
        $admin = $this->creerUtilisateur(1, 'admin@example.com');
        $this->configureControllerContainer($this->controller, $admin, ['ROLE_ADMIN']);

        $this->userRepository->method('find')->willReturn(null);

        $response = $this->controller->toggleEnabled(99, $this->userRepository, $this->entityManager);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testToggleEnabledSurSoiMemeRetourne400(): void
    {
        $admin = $this->creerUtilisateur(1, 'admin@example.com');
        $this->configureControllerContainer($this->controller, $admin, ['ROLE_ADMIN']);

        $this->userRepository->method('find')->willReturn($admin);

        $response = $this->controller->toggleEnabled(1, $this->userRepository, $this->entityManager);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            'Tu ne peux pas désactiver ton propre compte.',
            $this->decodeJsonResponse($response)['message']
        );
    }

    public function testToggleEnabledReussi(): void
    {
        $admin = $this->creerUtilisateur(1, 'admin@example.com');
        $target = $this->creerUtilisateur(2, 'user@example.com');

        $this->configureControllerContainer($this->controller, $admin, ['ROLE_ADMIN']);
        $this->userRepository->method('find')->willReturn($target);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->toggleEnabled(2, $this->userRepository, $this->entityManager);
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $data['id']);
        $this->assertFalse($data['enabled']);
    }

    public function testGetUserProfileUtilisateurIntrouvableRetourne404(): void
    {
        $admin = $this->creerUtilisateur(1, 'admin@example.com');
        $this->configureControllerContainer($this->controller, $admin, ['ROLE_ADMIN']);

        $this->userRepository->method('find')->willReturn(null);

        $response = $this->controller->getUserProfile(99, $this->userRepository);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testGetUserProfileRetourneProfilAlimentaire(): void
    {
        $admin = $this->creerUtilisateur(1, 'admin@example.com');
        $target = $this->creerUtilisateur(2, 'user@example.com');

        $profile = new UserFoodProfile();
        $profile->setGoalType(UserFoodProfile::GOAL_SPORTIF);
        $profile->setDietType(UserFoodProfile::DIET_VEGETARIEN);
        $profile->setIsHalal(true);
        $profile->setAllergies(['GLUTEN']);
        $profile->setUser($target);
        $target->setFoodProfile($profile);

        $this->configureControllerContainer($this->controller, $admin, ['ROLE_ADMIN']);
        $this->userRepository->method('find')->willReturn($target);

        $response = $this->controller->getUserProfile(2, $this->userRepository);
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('user@example.com', $data['email']);
        $this->assertSame(UserFoodProfile::GOAL_SPORTIF, $data['foodProfile']['goalType']);
    }

    private function creerUtilisateur(int $id, string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPseudo('pseudo' . $id);
        $user->setPrenom('Jean');
        $user->setNom('Dupont');
        $user->setAdresse('1 rue Test');
        $user->setVille('Paris');
        $user->setCodePostal(75001);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('hashed');
        $user->setEnabled(true);

        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, $id);

        return $user;
    }
}

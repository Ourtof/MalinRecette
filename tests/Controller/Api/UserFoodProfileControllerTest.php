<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\UserFoodProfileController;
use App\Entity\User;
use App\Entity\UserFoodProfile;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class UserFoodProfileControllerTest extends AbstractApiControllerTestCase
{
    private UserFoodProfileController $controller;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->controller = new UserFoodProfileController();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testGetProfileSansProfilRetourneValeursParDefaut(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getFoodProfile')->willReturn(null);

        $this->configureControllerContainer($this->controller, $user);

        $response = $this->controller->getProfile();
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(UserFoodProfile::GOAL_CLASSIQUE, $data['goalType']);
        $this->assertSame(UserFoodProfile::DIET_CLASSIQUE, $data['dietType']);
        $this->assertFalse($data['isHalal']);
        $this->assertSame([], $data['allergies']);
    }

    public function testGetProfileAvecProfilRetourneLesDonnees(): void
    {
        $profile = new UserFoodProfile();
        $profile->setGoalType(UserFoodProfile::GOAL_SPORTIF);
        $profile->setDietType(UserFoodProfile::DIET_VEGETARIEN);
        $profile->setIsHalal(true);
        $profile->setAllergies(['GLUTEN']);

        $user = $this->createMock(User::class);
        $user->method('getFoodProfile')->willReturn($profile);

        $this->configureControllerContainer($this->controller, $user);

        $response = $this->controller->getProfile();
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(UserFoodProfile::GOAL_SPORTIF, $data['goalType']);
        $this->assertSame(UserFoodProfile::DIET_VEGETARIEN, $data['dietType']);
        $this->assertTrue($data['isHalal']);
        $this->assertSame(['GLUTEN'], $data['allergies']);
    }

    public function testUpsertProfileJsonInvalideRetourne400(): void
    {
        $user = $this->createMock(User::class);
        $this->configureControllerContainer($this->controller, $user);

        $request = \Symfony\Component\HttpFoundation\Request::create('/api/me/food-profile', 'PUT', [], [], [], [], 'invalide');

        $response = $this->controller->upsertProfile($request, $this->entityManager);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('JSON invalide', $this->decodeJsonResponse($response)['error']);
    }

    public function testUpsertProfileDonneesInvalidesRetourneErreurs(): void
    {
        $user = $this->createMock(User::class);
        $this->configureControllerContainer($this->controller, $user);

        $request = $this->createJsonRequest('PUT', [
            'goalType' => 'INVALIDE',
            'dietType' => UserFoodProfile::DIET_CLASSIQUE,
            'isHalal' => 'oui',
            'allergies' => ['GLUTEN'],
        ]);

        $response = $this->controller->upsertProfile($request, $this->entityManager);
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertNotEmpty($data['errors']);
    }

    public function testUpsertProfileReussiCreeNouveauProfil(): void
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

        $this->configureControllerContainer($this->controller, $user);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $request = $this->createJsonRequest('PUT', [
            'goalType' => UserFoodProfile::GOAL_MINCEUR,
            'dietType' => UserFoodProfile::DIET_VEGETARIEN,
            'isHalal' => false,
            'allergies' => ['GLUTEN'],
        ]);

        $response = $this->controller->upsertProfile($request, $this->entityManager);
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(UserFoodProfile::GOAL_MINCEUR, $data['goalType']);
        $this->assertSame(UserFoodProfile::DIET_VEGETARIEN, $data['dietType']);
    }
}

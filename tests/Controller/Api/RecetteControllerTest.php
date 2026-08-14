<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\RecetteController;
use App\Entity\Illustration;
use App\Entity\Recette;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\UserFoodProfile;
use App\Repository\IllustrationRepository;
use App\Repository\RecetteRepository;
use App\Repository\TagRepository;
use App\Service\ServiceRecommandationRecettes;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class RecetteControllerTest extends AbstractApiControllerTestCase
{
    private RecetteController $controller;
    private RecetteRepository&MockObject $recetteRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private TagRepository&MockObject $tagRepository;
    private IllustrationRepository&MockObject $illustrationRepository;
    private ServiceRecommandationRecettes&MockObject $serviceRecommandation;

    protected function setUp(): void
    {
        $this->controller = new RecetteController();
        $this->configureControllerContainer($this->controller);

        $this->recetteRepository = $this->createMock(RecetteRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->illustrationRepository = $this->createMock(IllustrationRepository::class);
        $this->serviceRecommandation = $this->createMock(ServiceRecommandationRecettes::class);
    }

    public function testIndexRetourneListePaginee(): void
    {
        $recette = $this->creerRecette();

        $this->recetteRepository->method('search')->willReturn([
            'items' => [$recette],
            'total' => 1,
            'page' => 1,
            'limit' => 6,
        ]);

        $request = $this->createJsonRequest('GET');

        $response = $this->controller->index($request, $this->recetteRepository);
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $data['items']);
        $this->assertSame(1, $data['total']);
    }

    public function testShowRetourneRecette(): void
    {
        $recette = $this->creerRecette();

        $response = $this->controller->show($recette);
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Tarte aux pommes', $data['titre']);
    }

    public function testCreateSansAuthentificationRetourne401(): void
    {
        $this->configureControllerContainer($this->controller, null);

        $request = $this->createJsonRequest('POST', [
            'titre' => 'Test',
            'contenu' => 'Contenu',
            'illustrationId' => 1,
        ]);

        $response = $this->controller->create(
            $request,
            $this->entityManager,
            $this->tagRepository,
            $this->illustrationRepository
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCreateChampsObligatoiresManquantsRetourne400(): void
    {
        $user = $this->creerUtilisateur();
        $this->configureControllerContainer($this->controller, $user);

        $request = $this->createJsonRequest('POST', ['titre' => 'Sans contenu']);

        $response = $this->controller->create(
            $request,
            $this->entityManager,
            $this->tagRepository,
            $this->illustrationRepository
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateIllustrationIntrouvableRetourne400(): void
    {
        $user = $this->creerUtilisateur();
        $this->configureControllerContainer($this->controller, $user);

        $this->illustrationRepository->method('find')->willReturn(null);

        $request = $this->createJsonRequest('POST', [
            'titre' => 'Ma recette',
            'contenu' => 'Contenu',
            'illustrationId' => 99,
        ]);

        $response = $this->controller->create(
            $request,
            $this->entityManager,
            $this->tagRepository,
            $this->illustrationRepository
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Illustration introuvable', $this->decodeJsonResponse($response)['message']);
    }

    public function testCreateReussiRetourne201(): void
    {
        $user = $this->creerUtilisateur();
        $this->configureControllerContainer($this->controller, $user);

        $illustration = new Illustration();
        $illustration->setNomFichier('image.jpg');

        $reflection = new \ReflectionClass($illustration);
        $property = $reflection->getProperty('id');
        $property->setValue($illustration, 1);

        $this->illustrationRepository->method('find')->willReturn($illustration);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $request = $this->createJsonRequest('POST', [
            'titre' => 'Ma recette',
            'contenu' => 'Contenu de la recette',
            'illustrationId' => 1,
            'tagCodes' => [],
        ]);

        $response = $this->controller->create(
            $request,
            $this->entityManager,
            $this->tagRepository,
            $this->illustrationRepository
        );

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testRecommanderSansProfilRetourne400(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getFoodProfile')->willReturn(null);

        $this->configureControllerContainer($this->controller, $user);

        $response = $this->controller->recommander($this->serviceRecommandation);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testRecommanderSansRecetteRetourne204(): void
    {
        $profile = new UserFoodProfile();
        $user = $this->createMock(User::class);
        $user->method('getFoodProfile')->willReturn($profile);

        $this->configureControllerContainer($this->controller, $user);
        $this->serviceRecommandation->method('recommanderPourUtilisateur')->willReturn([]);

        $response = $this->controller->recommander($this->serviceRecommandation);

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testRecommanderRetourneMeilleureRecette(): void
    {
        $profile = new UserFoodProfile();
        $user = $this->createMock(User::class);
        $user->method('getFoodProfile')->willReturn($profile);

        $recette = $this->creerRecette();
        $tag = new Tag();
        $tag->setContenu('Healthy');
        $recette->addTag($tag);

        $reflection = new \ReflectionClass($recette);
        $property = $reflection->getProperty('id');
        $property->setValue($recette, 5);

        $this->configureControllerContainer($this->controller, $user);
        $this->serviceRecommandation->method('recommanderPourUtilisateur')->willReturn([$recette]);

        $response = $this->controller->recommander($this->serviceRecommandation);
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(5, $data['id']);
        $this->assertSame('Tarte aux pommes', $data['titre']);
        $this->assertContains('Healthy', $data['tags']);
    }

    public function testDeleteRecetteIntrouvableRetourne404(): void
    {
        $this->configureControllerContainer($this->controller, $this->creerUtilisateur(), ['ROLE_ADMIN']);
        $this->recetteRepository->method('find')->willReturn(null);

        $response = $this->controller->delete(99, $this->recetteRepository, $this->entityManager);

        $this->assertSame(404, $response->getStatusCode());
    }

    private function creerUtilisateur(): User
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPseudo('chef');
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

    private function creerRecette(): Recette
    {
        $user = $this->creerUtilisateur();

        $illustration = new Illustration();
        $illustration->setNomFichier('image.jpg');

        $recette = new Recette();
        $recette->setTitre('Tarte aux pommes');
        $recette->setContenu('Recette simple');
        $recette->setDateRecette(new \DateTime());
        $recette->setAuteur($user);
        $recette->setIllustration($illustration);

        return $recette;
    }
}

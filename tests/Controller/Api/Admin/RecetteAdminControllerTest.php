<?php

namespace App\Tests\Controller\Api\Admin;

use App\Controller\Api\Admin\RecetteAdminController;
use App\Entity\Illustration;
use App\Entity\Recette;
use App\Entity\User;
use App\Repository\IllustrationRepository;
use App\Repository\RecetteRepository;
use App\Repository\TagRepository;
use App\Tests\Controller\Api\AbstractApiControllerTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class RecetteAdminControllerTest extends AbstractApiControllerTestCase
{
    private RecetteAdminController $controller;
    private RecetteRepository&MockObject $recetteRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private TagRepository&MockObject $tagRepository;
    private IllustrationRepository&MockObject $illustrationRepository;

    protected function setUp(): void
    {
        $this->controller = new RecetteAdminController();
        $this->configureControllerContainer($this->controller, null, ['ROLE_ADMIN']);

        $this->recetteRepository = $this->createMock(RecetteRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->illustrationRepository = $this->createMock(IllustrationRepository::class);
    }

    public function testShowRecetteIntrouvableRetourne404(): void
    {
        $this->recetteRepository->method('find')->willReturn(null);

        $response = $this->controller->show(99, $this->recetteRepository);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testShowRetourneRecette(): void
    {
        $recette = $this->creerRecette();
        $this->recetteRepository->method('find')->willReturn($recette);

        $response = $this->controller->show(1, $this->recetteRepository);
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Salade composée', $data['titre']);
    }

    public function testUpdateJsonInvalideRetourne400(): void
    {
        $recette = $this->creerRecette();
        $this->recetteRepository->method('find')->willReturn($recette);

        $request = \Symfony\Component\HttpFoundation\Request::create('/api/admin/recette/1', 'PUT', [], [], [], [], 'invalide');

        $response = $this->controller->update(
            1,
            $request,
            $this->recetteRepository,
            $this->entityManager,
            $this->tagRepository,
            $this->illustrationRepository
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUpdateTitreVideRetourne400(): void
    {
        $recette = $this->creerRecette();
        $this->recetteRepository->method('find')->willReturn($recette);

        $request = $this->createJsonRequest('PUT', ['titre' => '   ']);

        $response = $this->controller->update(
            1,
            $request,
            $this->recetteRepository,
            $this->entityManager,
            $this->tagRepository,
            $this->illustrationRepository
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Le titre ne peut pas être vide', $this->decodeJsonResponse($response)['message']);
    }

    public function testUpdateReussi(): void
    {
        $recette = $this->creerRecette();
        $this->recetteRepository->method('find')->willReturn($recette);
        $this->entityManager->expects($this->once())->method('flush');

        $request = $this->createJsonRequest('PUT', ['titre' => 'Nouveau titre']);

        $response = $this->controller->update(
            1,
            $request,
            $this->recetteRepository,
            $this->entityManager,
            $this->tagRepository,
            $this->illustrationRepository
        );

        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Nouveau titre', $data['titre']);
    }

    public function testDeleteRecetteIntrouvableRetourne404(): void
    {
        $this->recetteRepository->method('find')->willReturn(null);

        $response = $this->controller->delete(99, $this->recetteRepository, $this->entityManager);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteReussiRetourne204(): void
    {
        $recette = $this->creerRecette();
        $this->recetteRepository->method('find')->willReturn($recette);

        $this->entityManager->expects($this->once())->method('remove')->with($recette);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->delete(1, $this->recetteRepository, $this->entityManager);

        $this->assertSame(204, $response->getStatusCode());
    }

    private function creerRecette(): Recette
    {
        $user = new User();
        $user->setEmail('chef@example.com');
        $user->setPseudo('chef');
        $user->setPrenom('Jean');
        $user->setNom('Dupont');
        $user->setAdresse('1 rue Test');
        $user->setVille('Paris');
        $user->setCodePostal(75001);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('hashed');

        $illustration = new Illustration();
        $illustration->setNomFichier('image.jpg');

        $recette = new Recette();
        $recette->setTitre('Salade composée');
        $recette->setContenu('Une bonne salade');
        $recette->setDateRecette(new \DateTime());
        $recette->setAuteur($user);
        $recette->setIllustration($illustration);

        $reflection = new \ReflectionClass($recette);
        $property = $reflection->getProperty('id');
        $property->setValue($recette, 1);

        return $recette;
    }
}

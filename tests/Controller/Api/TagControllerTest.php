<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\TagController;
use App\Entity\Tag;
use App\Repository\TagRepository;
use PHPUnit\Framework\MockObject\MockObject;

class TagControllerTest extends AbstractApiControllerTestCase
{
    private TagController $controller;
    private TagRepository&MockObject $tagRepository;

    protected function setUp(): void
    {
        $this->controller = new TagController();
        $this->configureControllerContainer($this->controller);
        $this->tagRepository = $this->createMock(TagRepository::class);
    }

    public function testIndexRetourneTousLesTagsActifs(): void
    {
        $tag = new Tag();
        $tag->setCode('HEALTHY');
        $tag->setContenu('Healthy');
        $tag->setCategorie(Tag::CATEGORIE_OBJECTIF);

        $this->tagRepository->expects($this->once())
            ->method('findBy')
            ->with(
                ['isActive' => true],
                ['categorie' => 'ASC', 'contenu' => 'ASC']
            )
            ->willReturn([$tag]);

        $request = $this->createJsonRequest('GET');

        $response = $this->controller->index($request, $this->tagRepository);
        $data = $this->decodeJsonResponse($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $data);
        $this->assertSame('HEALTHY', $data[0]['code']);
    }

    public function testIndexAvecCategorieFiltreLesTags(): void
    {
        $this->tagRepository->expects($this->once())
            ->method('findBy')
            ->with(
                ['isActive' => true, 'categorie' => 'ALLERGENE'],
                ['categorie' => 'ASC', 'contenu' => 'ASC']
            )
            ->willReturn([]);

        $request = $this->createJsonRequest('GET', [], ['categorie' => 'ALLERGENE']);

        $response = $this->controller->index($request, $this->tagRepository);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->decodeJsonResponse($response));
    }
}

<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\IllustrationController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class IllustrationControllerTest extends AbstractApiControllerTestCase
{
    private IllustrationController $controller;
    private EntityManagerInterface&MockObject $entityManager;
    private ParameterBagInterface&MockObject $params;

    protected function setUp(): void
    {
        $this->controller = new IllustrationController();
        $this->configureControllerContainer($this->controller);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
    }

    public function testUploadSansContenuRetourne400(): void
    {
        $request = Request::create('/api/illustrations', 'POST');

        $response = $this->controller->upload(
            $request,
            $this->entityManager,
            $this->params
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Aucun fichier envoyé', $this->decodeJsonResponse($response)['message']);
    }

    public function testUploadFichierTropVolumineuxRetourne400(): void
    {
        $request = Request::create(
            '/api/illustrations',
            'POST',
            [],
            [],
            [],
            [],
            str_repeat('a', 5 * 1024 * 1024 + 1)
        );

        $response = $this->controller->upload(
            $request,
            $this->entityManager,
            $this->params
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Fichier trop volumineux (max 5 Mo)', $this->decodeJsonResponse($response)['message']);
    }

    public function testUploadTypeNonAutoriseRetourne400(): void
    {
        $request = Request::create(
            '/api/illustrations',
            'POST',
            [],
            [],
            [],
            [],
            'contenu-texte-non-image'
        );

        $response = $this->controller->upload(
            $request,
            $this->entityManager,
            $this->params
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Type de fichier non autorisé', $this->decodeJsonResponse($response)['message']);
    }

    public function testUploadImageValideRetourne201(): void
    {
        $projectDir = sys_get_temp_dir() . '/illustration_test_' . uniqid();
        $this->params->method('get')->with('kernel.project_dir')->willReturn($projectDir);

        $imageContent = $this->creerImagePng1x1();

        $request = Request::create(
            '/api/illustrations',
            'POST',
            [],
            [],
            [],
            ['HTTP_X-Filename' => 'test.png'],
            $imageContent
        );

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->upload(
            $request,
            $this->entityManager,
            $this->params
        );

        $data = $this->decodeJsonResponse($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertArrayHasKey('nomFichier', $data);

        $this->supprimerRepertoire($projectDir);
    }

    private function creerImagePng1x1(): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Extension GD non disponible');
        }

        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return ob_get_clean() ?: '';
    }

    private function supprimerRepertoire(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}

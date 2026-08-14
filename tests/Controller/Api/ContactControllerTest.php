<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\ContactController;
use App\Entity\Contact;
use App\Service\EmailValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ContactControllerTest extends AbstractApiControllerTestCase
{
    private ContactController $controller;
    private EntityManagerInterface&MockObject $entityManager;
    private EmailValidatorService&MockObject $emailValidator;

    protected function setUp(): void
    {
        $this->controller = new ContactController();
        $this->configureControllerContainer($this->controller);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->emailValidator = $this->createMock(EmailValidatorService::class);
    }

    public function testJsonInvalideRetourne400(): void
    {
        $request = \Symfony\Component\HttpFoundation\Request::create('/api/contact', 'POST', [], [], [], [], 'invalide');

        $response = $this->controller->create(
            $request,
            $this->entityManager,
            $this->emailValidator
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testChampsObligatoiresManquantsRetourne400(): void
    {
        $request = $this->createJsonRequest('POST', ['adresseMail' => 'test@example.com']);

        $response = $this->controller->create(
            $request,
            $this->entityManager,
            $this->emailValidator
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            'adresseMail et contenuMessage sont obligatoires',
            $this->decodeJsonResponse($response)['message']
        );
    }

    public function testEmailInvalideRetourne400(): void
    {
        $this->emailValidator->method('validateAndNormalize')->willReturn([
            'valid' => false,
            'error' => 'Email invalide',
        ]);

        $request = $this->createJsonRequest('POST', [
            'adresseMail' => 'pas-un-email',
            'contenuMessage' => 'Bonjour',
        ]);

        $response = $this->controller->create(
            $request,
            $this->entityManager,
            $this->emailValidator
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            'adresseMail n\'est pas une adresse valide',
            $this->decodeJsonResponse($response)['message']
        );
    }

    public function testCreationReussieRetourne201(): void
    {
        $this->emailValidator->method('validateAndNormalize')->willReturn([
            'valid' => true,
            'email' => 'contact@example.com',
        ]);

        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Contact::class));
        $this->entityManager->expects($this->once())->method('flush');

        $request = $this->createJsonRequest('POST', [
            'adresseMail' => 'contact@example.com',
            'contenuMessage' => '  Message de test  ',
        ]);

        $response = $this->controller->create(
            $request,
            $this->entityManager,
            $this->emailValidator
        );

        $data = $this->decodeJsonResponse($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('contact@example.com', $data['adresseMail']);
        $this->assertSame('Message de test', $data['contenuMessage']);
    }
}

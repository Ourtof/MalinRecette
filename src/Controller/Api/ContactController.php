<?php

namespace App\Controller\Api;

use App\Controller\Api\Traits\JsonRequestTrait;
use App\Entity\Contact;
use App\Service\EmailValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/contact', name: 'api_contact_')]
class ContactController extends AbstractController
{
    use JsonRequestTrait;

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        EmailValidatorService $emailValidator
    ): JsonResponse {
        $payload = $this->getJsonData($request);
        if ($payload === null) {
            return $this->jsonInvalidResponse();
        }

        $message = trim((string)($payload['contenuMessage'] ?? ''));

        if (empty($payload['adresseMail']) || $message === '') {
            return $this->json(
                ['message' => 'adresseMail et contenuMessage sont obligatoires'],
                400
            );
        }

        $emailValidation = $emailValidator->validateAndNormalize($payload['adresseMail']);
        if (!$emailValidation['valid']) {
            return $this->json(
                ['message' => 'adresseMail n\'est pas une adresse valide'],
                400
            );
        }
        $email = $emailValidation['email'];

        $contact = new Contact();
        $contact
            ->setAdresseMail($email)
            ->setContenuMessage($message);

        $em->persist($contact);
        $em->flush();

        return $this->json([
            'id'            => $contact->getId(),
            'adresseMail'   => $contact->getAdresseMail(),
            'contenuMessage'=> $contact->getContenuMessage(),
        ], 201);
    }
}

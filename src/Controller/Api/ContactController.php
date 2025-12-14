<?php

namespace App\Controller\Api;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/contact', name: 'api_contact_')]
class ContactController extends AbstractController
{
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json(['message' => 'JSON invalide'], 400);
        }

        $email   = trim((string)($payload['adresseMail'] ?? ''));
        $message = trim((string)($payload['contenuMessage'] ?? ''));

        if ($email === '' || $message === '') {
            return $this->json(
                ['message' => 'adresseMail et contenuMessage sont obligatoires'],
                400
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(
                ['message' => 'adresseMail n\'est pas une adresse valide'],
                400
            );
        }

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

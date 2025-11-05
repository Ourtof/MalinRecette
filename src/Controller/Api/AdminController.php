<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminController extends AbstractController
{
    #[Route('/api/admin', name: 'admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): JsonResponse
    {
        return $this->json(['message' => 'Accès autorisé — ADMIN uniquement',
        'whoami' => $this->getUser()->getUserIdentifier()
        ],
        Response::HTTP_OK);
    }
}

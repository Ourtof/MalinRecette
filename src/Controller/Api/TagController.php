<?php

namespace App\Controller\Api;

use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TagController extends AbstractController
{
    #[Route('/api/tags', name: 'api_tags_index', methods: ['GET'])]
    public function index(TagRepository $tagRepository): JsonResponse
    {
        // Récupère tous les contenu des tags
        $rows = $tagRepository->createQueryBuilder('t')
            ->select('t.contenu')
            ->orderBy('t.contenu', 'ASC')
            ->getQuery()
            ->getArrayResult();
        $tags = array_column($rows, 'contenu');

        return $this->json($tags);
    }
}

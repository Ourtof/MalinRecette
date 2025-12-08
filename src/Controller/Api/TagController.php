<?php

namespace App\Controller\Api;

use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TagController extends AbstractController
{
    #[Route('/api/tags', name: 'api_tags_index', methods: ['GET'])]
    public function index(Request $request, TagRepository $tagRepository): JsonResponse
    {
        $categorie = $request->query->get('categorie'); // ex: OBJECTIF / ALLERGENE

        $criteres = ['isActive' => true];

        if (!empty($categorie)) {
            $criteres['categorie'] = $categorie;
        }

        $tags = $tagRepository->findBy(
            $criteres,
            ['categorie' => 'ASC', 'contenu' => 'ASC']
        );

        $data = array_map(function ($tag) {
            return [
                'code'      => $tag->getCode(),
                'contenu'   => $tag->getContenu(),
                'categorie' => $tag->getCategorie(),
            ];
        }, $tags);

        return $this->json($data);
    }
}

<?php

namespace App\Controller\Api;

use App\Service\ServiceRecommandationRecettes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me/recommandations')]
#[IsGranted('ROLE_USER')]
class RecommandationController extends AbstractController
{
    public function __construct(
        private ServiceRecommandationRecettes $serviceRecommandationRecettes
    ) {
    }

    #[Route('', name: 'api_me_recommandations', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        /** @var \App\Entity\User $utilisateur */
        $utilisateur = $this->getUser();
        
        $recettes = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur, 20);

        $donnees = [];

        foreach ($recettes as $recette) {
            $tags = [];
            foreach ($recette->getTags() as $tag) {
                $tags[] = $tag->getContenu();
            }

            $donnees[] = [
                'id'    => $recette->getId(),
                'titre' => $recette->getTitre(),
                'tags'  => $tags,
            ];
        }

        return $this->json($donnees);
    }
}

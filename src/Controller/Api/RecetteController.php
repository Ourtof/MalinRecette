<?php

namespace App\Controller\Api;

use App\Entity\Recette;
use App\Entity\Tag;
use App\Repository\RecetteRepository;
use App\Repository\TagRepository;
use App\Repository\IllustrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/recettes', name: 'api_recettes_')]
class RecetteController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, RecetteRepository $recetteRepo): JsonResponse
    {
        $q = $request->query->get('q');
        $tag = $request->query->get('tag');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);

        $result = $recetteRepo->search($q, $tag, $page, $limit);

        $items = array_map([$this, 'normalizeRecette'], $result['items']);

        return $this->json([
            'items' => $items,
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Recette $recette): JsonResponse
    {
        return $this->json($this->normalizeRecette($recette));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        TagRepository $tagRepo,
        IllustrationRepository $illustrationRepo,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            // #[IsGranted] devrait suffire mais on garde un filet
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'JSON invalide'], 400);
        }

        $titre = trim($payload['titre'] ?? '');
        $contenu = trim($payload['contenu'] ?? '');

        if ($titre === '' || $contenu === '') {
            return $this->json([
                'message' => 'Titre et contenu sont obligatoires',
            ], 400);
        }

        // 🔴 Illustration OBLIGATOIRE
        if (empty($payload['illustrationId'])) {
            return $this->json([
                'message' => 'illustrationId est obligatoire',
            ], 400);
        }

        $illustration = $illustrationRepo->find((int) $payload['illustrationId']);
        if (!$illustration) {
            return $this->json([
                'message' => 'Illustration introuvable',
            ], 400);
        }

        $recette = new Recette();
        $recette->setTitre($titre);
        $recette->setContenu($contenu);
        $recette->setDateRecette(new \DateTime());
        $recette->setAuteur($user);
        $recette->setIllustration($illustration);

        // Gestion des tags : tableau de noms ["rapide", "pâtes", ...]
        $tags = $payload['tags'] ?? [];
        if (is_array($tags)) {
            foreach ($tags as $tagName) {
                $tagName = trim((string) $tagName);
                if ($tagName === '') {
                    continue;
                }

                $tag = $tagRepo->findOneBy(['contenu' => $tagName]);
                if (!$tag) {
                    $tag = new Tag();
                    $tag->setContenu($tagName);
                    $em->persist($tag);
                }

                $recette->addTag($tag);
            }
        }

        $em->persist($recette);
        $em->flush();

        return $this->json($this->normalizeRecette($recette), 201);
    }

    private function normalizeRecette(Recette $recette): array
    {
        $auteur = $recette->getAuteur();
        $illustration = $recette->getIllustration();

        return [
            'id' => $recette->getId(),
            'titre' => $recette->getTitre(),
            'contenu' => $recette->getContenu(),
            'dateRecette' => $recette->getDateRecette()?->format(\DateTime::ATOM),

            'auteur' => $auteur ? [
                'id' => $auteur->getId(),
                'pseudo' => method_exists($auteur, 'getPseudo') && $auteur->getPseudo()
                    ? $auteur->getPseudo()
                    : $auteur->getEmail(),
            ] : null,

            'illustration' => $illustration ? [
                'id' => $illustration->getId(),
                'nomFichier' => $illustration->getNomFichier(),
            ] : null,

            'tags' => array_map(
                function (Tag $tag) {
                    return [
                        'id' => $tag->getId(),
                        'contenu' => $tag->getContenu(),
                    ];
                },
                $recette->getTags()->toArray()
            ),
        ];
    }
}

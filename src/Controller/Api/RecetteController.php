<?php

namespace App\Controller\Api;

use App\Entity\Recette;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\RecetteRepository;
use App\Repository\TagRepository;
use App\Repository\IllustrationRepository;
use App\Service\ServiceRecommandationRecettes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
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

        // Illustration OBLIGATOIRE
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

// 👉 Gestion des tags : tableau de codes ["HEALTHY", "HALAL", "GLUTEN", ...]
$tagCodes = $payload['tagCodes'] ?? [];

if (!is_array($tagCodes)) {
    return $this->json([
        'message' => 'tagCodes doit être un tableau de codes',
    ], 400);
}

$allergies = [];

if (!empty($tagCodes)) {
    $tags = $tagRepo->findBy([
        'code'     => $tagCodes,
        'isActive' => true,
    ]);

    $codesTrouves = array_map(fn(Tag $tag) => $tag->getCode(), $tags);
    $codesManquants = array_diff($tagCodes, $codesTrouves);

    if (!empty($codesManquants)) {
        return $this->json([
            'message' => 'Certains tagCodes sont inconnus ou inactifs',
            'detail'  => array_values($codesManquants),
        ], 400);
    }

    foreach ($tags as $tag) {
        $recette->addTag($tag);

        // Si c'est un allergène, on enregistre son code
        if ($tag->getCategorie() === 'ALLERGENE') {
            $allergies[] = $tag->getCode(); // ex. "GLUTEN"
        }
    }
}

// Sauvegarde du JSON ["GLUTEN", "LACTOSE", ...]
$recette->setAllergies($allergies);

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
                'nomFichier'=> $illustration->getNomFichier(),
            ] : null,

            'tags' => array_map(
                function (Tag $tag) {
                    return [
                        'id' => $tag->getId(),
                        'code' => $tag->getCode(),
                        'contenu' => $tag->getContenu(),
                        'categorie' => $tag->getCategorie(),
                    ];
                },
                $recette->getTags()->toArray()
            ),
        ];
    }

      #[Route('/recommandation', name: 'recommandation', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function recommander(
        ServiceRecommandationRecettes $serviceRecommandation
    ): JsonResponse {
        /** @var User|null $utilisateur */
        $utilisateur = $this->getUser();

        if (!$utilisateur instanceof User) {

            // Filet de sécurité en plus de #[IsGranted]
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        // Option : on impose d'avoir un profil, sinon 400
        if (!$utilisateur->getFoodProfile()) {
            return $this->json(
                ['message' => 'Profil alimentaire non défini pour cet utilisateur'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Appel de ton service existant
        $recettes = $serviceRecommandation->recommanderPourUtilisateur($utilisateur, 20);

        if (empty($recettes)) {
            return $this->json([], Response::HTTP_OK);
        }

        $donnees = array_map(function (Recette $recette) {
            $tags = [];

            foreach ($recette->getTags() as $tag) {
                if ($tag instanceof Tag) {
                    $tags[] = $tag->getContenu();
                }
            }

            return [
                'id'    => $recette->getId(),
                'titre' => $recette->getTitre(),
                'tags'  => $tags,
            ];
        }, $recettes);

        return $this->json($donnees, Response::HTTP_OK);
    }
}

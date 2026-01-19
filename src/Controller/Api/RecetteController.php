<?php

namespace App\Controller\Api;

use App\Controller\Api\Traits\JsonRequestTrait;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/recettes', name: 'api_recettes_')]
class RecetteController extends AbstractController
{
    use JsonRequestTrait;
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, RecetteRepository $recetteRepo): JsonResponse
    {
        $q = $request->query->get('q');
        $tag = $request->query->get('tag');
        $page  = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 6);
        // limite les abus
        $limit = min(max(1, $limit), 100);

        $result = $recetteRepo->search($q, $tag, $page, $limit);

        $items = array_map([$this, 'normalizeRecette'], $result['items']);

        return $this->json([
            'items' => $items,
            'total' => $result['total'],
            'page'  => $result['page'],
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
        if (!$user instanceof User) {
            // sécurité supplémentaire à isGranted
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $payload = $this->getJsonData($request);
        if ($payload === null) {
            return $this->jsonInvalidResponse();
        }

        $titre = trim($payload['titre'] ?? '');
        $contenu = trim($payload['contenu'] ?? '');

        if ($titre === '' || $contenu === '') {
            return $this->json([
                'message' => 'Titre et contenu sont obligatoires',
            ], 400);
        }

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

        // gestion des tags : tableau de codes ["HEALTHY", "HALAL", "GLUTEN", ...]
        $tagCodes = $payload['tagCodes'] ?? [];

        if (!is_array($tagCodes)) {
            return $this->json([
                'message' => 'tagCodes doit être un tableau de codes',
            ], 400);
        }

        // limite tag
        if (count($tagCodes) > 10) {
            return $this->json([
                'message' => 'Maximum 20 tags autorisés par recette'
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

                // si c'est un allergène, on enregistre son code
                if ($tag->getCategorie() === 'ALLERGENE') {
                    $allergies[] = $tag->getCode(); // ex. "GLUTEN"
                }
            }
        }

        $recette->setAllergies($allergies);

        $em->persist($recette);
        $em->flush();

        return $this->json($this->normalizeRecette($recette), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        int $id,
        Request $request,
        RecetteRepository $recetteRepo,
        EntityManagerInterface $em,
        TagRepository $tagRepo,
        IllustrationRepository $illustrationRepo,
    ): JsonResponse {
        /** @var Recette|null $recette */
        $recette = $recetteRepo->find($id);

        if (!$recette) {
            return $this->json(['message' => 'Recette introuvable'], 404);
        }

        $payload = $this->getJsonData($request);
        if ($payload === null) {
            return $this->jsonInvalidResponse();
        }

        if (array_key_exists('titre', $payload)) {
            $titre = trim((string) $payload['titre']);
            if ($titre === '') {
                return $this->json(['message' => 'Le titre ne peut pas être vide'], 400);
            }
            $recette->setTitre($titre);
        }

        if (array_key_exists('contenu', $payload)) {
            $contenu = trim((string) $payload['contenu']);
            if ($contenu === '') {
                return $this->json(['message' => 'Le contenu ne peut pas être vide'], 400);
            }
            $recette->setContenu($contenu);
        }

        if (array_key_exists('illustrationId', $payload)) {
            if (empty($payload['illustrationId'])) {
                return $this->json(['message' => 'illustrationId ne peut pas être vide'], 400);
            }

            $illustration = $illustrationRepo->find((int) $payload['illustrationId']);
            if (!$illustration) {
                return $this->json(['message' => 'Illustration introuvable'], 400);
            }

            $recette->setIllustration($illustration);
        }

        if (array_key_exists('tagCodes', $payload)) {
            $tagCodes = $payload['tagCodes'];

            if (!is_array($tagCodes)) {
                return $this->json([
                    'message' => 'tagCodes doit être un tableau de codes',
                ], 400);
            }

            // limite le nombre de tag
            if (count($tagCodes) > 20) {
                return $this->json([
                    'message' => 'Maximum 20 tags autorisés par recette'
                ], 400);
            }

            foreach ($recette->getTags() as $existingTag) {
                $recette->removeTag($existingTag);
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

                    if ($tag->getCategorie() === 'ALLERGENE') {
                        $allergies[] = $tag->getCode();
                    }
                }
            }

            $recette->setAllergies($allergies);
        }

        $em->flush();

        return $this->json($this->normalizeRecette($recette), 200);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        int $id,
        RecetteRepository $recetteRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var Recette|null $recette */
        $recette = $recetteRepo->find($id);

        if (!$recette) {
            return $this->json(['message' => 'Recette introuvable'], 404);
        }

        $em->remove($recette);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function normalizeRecette(Recette $recette): array
    {
        $auteur       = $recette->getAuteur();
        $illustration = $recette->getIllustration();

        $auteurArray = null;

        if ($auteur instanceof User) {
            $pseudo = $auteur->getPseudo();

            // Si le compte est désactivé, on anonymise l’affichage
            if (!$auteur->isEnabled()) {
                $pseudo = 'Ancien utilisateur';
            }

            $auteurArray = [
                'id'     => $auteur->getId(),
                'pseudo' => $pseudo,
            ];
        }

        return [
            'id'          => $recette->getId(),
            'titre'       => $recette->getTitre(),
            'contenu'     => $recette->getContenu(),
            'dateRecette' => $recette->getDateRecette()?->format(\DateTime::ATOM),

            'auteur'      => $auteurArray,

            'illustration' => $illustration ? [
                'id'         => $illustration->getId(),
                'nomFichier' => $illustration->getNomFichier(),
            ] : null,

            'tags' => array_map(
                function (Tag $tag) {
                    return [
                        'id'        => $tag->getId(),
                        'code'      => $tag->getCode(),
                        'contenu'   => $tag->getContenu(),
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
            // filet de sécurité en plus de isGranted
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        // profil obligatoire sinon 400
        if (!$utilisateur->getFoodProfile()) {
            return $this->json(
                ['message' => 'Profil alimentaire non défini pour cet utilisateur'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // pour l’API on ne garde que le meilleur résultat
        $recettes = $serviceRecommandation->recommanderPourUtilisateur($utilisateur, 20);

        // aucune recette adaptée retourne 204
        if (empty($recettes)) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        /** @var Recette $recette */
        $recette = $recettes[0];

        $tags = array_map(
            static fn (Tag $tag) => $tag->getContenu(),
            $recette->getTags()->toArray()
        );

        $donnees = [
            'id'    => $recette->getId(),
            'titre' => $recette->getTitre(),
            'tags'  => $tags,
        ];

        return $this->json($donnees, Response::HTTP_OK);
    }
}

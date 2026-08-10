<?php

namespace App\Controller\Api\Admin;

use App\Entity\Recette;
use App\Entity\Tag;
use App\Repository\RecetteRepository;
use App\Repository\TagRepository;
use App\Repository\IllustrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class RecetteAdminController extends AbstractController
{
    #[Route('/recette', name: 'api_admin_recette_index', methods: ['GET'])]
    public function index(Request $request, RecetteRepository $recetteRepository): JsonResponse
    {
        // récup paramètre de requête
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 20);
        $limit = max(1, min(100, $limit));
        $search = trim((string) $request->query->get('search', ''));

        // base du QueryBuilder
        $qb = $recetteRepository->createQueryBuilder('r')
            ->leftJoin('r.auteur', 'a')
            ->addSelect('a')
            ->leftJoin('r.illustration', 'i')
            ->addSelect('i');

        // filtre recherche
        if ($search !== '') {
            $qb
                ->andWhere('r.titre LIKE :search OR r.contenu LIKE :search OR a.pseudo LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        // total avant pagination
        $qbCount = clone $qb;
        $total = (int) $qbCount
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // pagination + sélection des champs
        $qb
            ->orderBy('r.dateRecette', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $recettes = $qb->getQuery()->getResult();
        $items = array_map([$this, 'normalizeRecette'], $recettes);

        // format de réponse standardisé
        return $this->json([
            'items' => $items,
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    #[Route('/recette/{id}', name: 'api_admin_recette_show', methods: ['GET'])]
    public function show(int $id, RecetteRepository $recetteRepository): JsonResponse
    {
        /** @var Recette|null $recette */
        $recette = $recetteRepository->find($id);

        if (!$recette) {
            return $this->json(['message' => 'Recette introuvable'], 404);
        }

        return $this->json($this->normalizeRecette($recette));
    }

    #[Route('/recette/{id}', name: 'api_admin_recette_update', methods: ['PUT'])]
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

        $payload = json_decode($request->getContent(), true);
        if ($payload === null) {
            return $this->json(['message' => 'Données JSON invalides'], 400);
        }

        // mise à jour titre
        if (isset($payload['titre'])) {
            $titre = trim($payload['titre']);
            if ($titre === '') {
                return $this->json(['message' => 'Le titre ne peut pas être vide'], 400);
            }
            $recette->setTitre($titre);
        }

        // mise à jour contenu
        if (isset($payload['contenu'])) {
            $contenu = trim($payload['contenu']);
            if ($contenu === '') {
                return $this->json(['message' => 'Le contenu ne peut pas être vide'], 400);
            }
            $recette->setContenu($contenu);
        }

        // mise à jour illustration
        if (isset($payload['illustrationId'])) {
            $illustration = $illustrationRepo->find((int) $payload['illustrationId']);
            if (!$illustration) {
                return $this->json(['message' => 'Illustration introuvable'], 400);
            }
            $recette->setIllustration($illustration);
        }

        // mise à jour tags
        if (isset($payload['tagCodes'])) {
            $tagCodes = $payload['tagCodes'];
            if (!is_array($tagCodes)) {
                return $this->json(['message' => 'tagCodes doit être un tableau'], 400);
            }

            if (count($tagCodes) > 20) {
                return $this->json(['message' => 'Maximum 20 tags autorisés par recette'], 400);
            }

            // supprime tous les tags existants
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

    #[Route('/recette/{id}', name: 'api_admin_recette_delete', methods: ['DELETE'])]
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

        return new JsonResponse(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRecette(Recette $recette): array
    {
        $auteur = $recette->getAuteur();
        $illustration = $recette->getIllustration();

        $auteurArray = null;
        if ($auteur) {
            $auteurArray = [
                'id'     => $auteur->getId(),
                'pseudo' => $auteur->getPseudo() ?? 'Ancien utilisateur',
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
            'allergies' => $recette->getAllergies(),
        ];
    }
}

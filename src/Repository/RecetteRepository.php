<?php

namespace App\Repository;

use App\Entity\Recette;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recette>
 */
class RecetteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recette::class);
    }

    /**
     * Recherche paginée par texte et tag.
     *
     * @return array{items: Recette[], total: int, page: int, limit: int}
     */
    public function search(?string $q, ?string $tag, int $page = 1, int $limit = 6): array
    {
        $page = max(1, $page);

        // 1️⃣ D'abord, on récupère les IDs des recettes qui correspondent aux critères
        $qb = $this->createQueryBuilder('r');
        
        if ($tag !== null && $tag !== '') {
            $qb->leftJoin('r.tags', 't');
        }

        $qb->select('r.id')
           ->orderBy('r.dateRecette', 'DESC');

        /* Filtre texte optionnel :
           Si q est rempli, on ajoute un WHERE :
           titre LIKE %q% ou contenu LIKE %q%
           Donc recherche simple plein texte sur titre + contenu.*/
        if ($q !== null && $q !== '') {
            $qb
                ->andWhere('r.titre LIKE :q OR r.contenu LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }

        /* Filtre par tag optionnel :
           Si tag est rempli, on filtre seulement les recettes qui ont un tag dont Tag.contenu = :tag.
           Typiquement pour un futur filtre : ?tag=rapide ou ?tag=vegan. */
        if ($tag !== null && $tag !== '') {
            $qb
                ->andWhere('t.contenu = :tag')
                ->setParameter('tag', $tag);
        }

        // Total pour la pagination
        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Récupération des IDs paginés (avec DISTINCT pour éviter les doublons)
        $qb
            ->distinct()
            ->setFirstResult(($page - 1) * $limit) // offset (par ex. page 2, limit 6 → offset 6).
            ->setMaxResults($limit); // nombre de lignes retournées.

        $ids = array_map(fn($row) => $row['id'], $qb->getQuery()->getResult());

        // Si aucun résultat
        if (empty($ids)) {
            return [
                'items' => [],
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        }

        // 2️⃣ Ensuite, on charge les recettes complètes avec toutes leurs relations
        // leftJoin('r.tags', 't') + addSelect('t') = tu récupères aussi les Tag d'un coup (évite le N+1 query quand tu loops sur getTags()).
        $items = $this->createQueryBuilder('r')
            ->leftJoin('r.tags', 't')
            ->addSelect('t')
            ->leftJoin('r.auteur', 'a')
            ->addSelect('a')
            ->leftJoin('r.illustration', 'i')
            ->addSelect('i')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('r.dateRecette', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
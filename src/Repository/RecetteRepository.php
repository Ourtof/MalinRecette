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
    public function search(?string $q, ?string $tag, int $page = 1, int $limit = 10): array
    {
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));

        $qb = $this->createQueryBuilder('r') // r = alias pour Recette.
            ->leftJoin('r.tags', 't') // leftJoin('r.tags', 't') + addSelect('t') = tu récupères aussi les Tag d’un coup (évite le N+1 query quand tu loops sur getTags()).
            ->addSelect('t')
            ->leftJoin('r.auteur', 'a')
            ->addSelect('a')
            ->leftJoin('r.illustration', 'i')
            ->addSelect('i')
            ->orderBy('r.dateRecette', 'DESC')
        ;

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

        // Résultats paginés
        $qb
            ->setFirstResult(($page - 1) * $limit) // offset (par ex. page 2, limit 10 → offset 10).
            ->setMaxResults($limit) // nombre de lignes retournées.
        ;

        // On récupère les Recette[] (avec leurs tags, auteur, illustration déjà peuplés grâce aux leftJoin + addSelect).
        /** @var Recette[] $items */
        $items = $qb
        ->getQuery()
        ->getResult();
        
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];

        // Résultat : ton contrôleur peut facilement renvoyer un JSON
    }
}

<?php

namespace App\Service;

use App\Entity\Recette;
use App\Entity\User;
use App\Entity\UserFoodProfile;
use App\Repository\RecetteRepository;

class ServiceRecommandationRecettes
{
    public function __construct(
        private RecetteRepository $recetteRepository
    ) {
    }

    /**
     * @return Recette[]
     */
    public function recommanderPourUtilisateur(User $utilisateur, int $limite = 20): array
    {
        $profil = $utilisateur->getFoodProfile();

        $objectif  = $profil?->getGoalType()  ?? UserFoodProfile::GOAL_CLASSIQUE;
        $regime    = $profil?->getDietType()  ?? UserFoodProfile::DIET_CLASSIQUE;
        $estHalal  = $profil?->isHalal()      ?? false;
        $allergies = $profil?->getAllergies() ?? [];

        $recettes = $this->recetteRepository->findAll();
        $recettesFiltrees = [];

        foreach ($recettes as $recette) {
            if (!$this->correspondAuRegime($recette, $regime)) {
                continue;
            }
            if (!$this->correspondAuHalal($recette, $estHalal)) {
                continue;
            }
            if (!$this->correspondALObjectif($recette, $objectif)) {
                continue;
            }
            if (!$this->compatibleAvecAllergies($recette, $allergies)) {
                continue;
            }

            $recettesFiltrees[] = $recette;
        }

        shuffle($recettesFiltrees);

        if ($limite > 0) {
            $recettesFiltrees = array_slice($recettesFiltrees, 0, $limite);
        }

        return $recettesFiltrees;
    }

    private function correspondAuRegime(Recette $recette, string $regime): bool
    {
        if ($regime === UserFoodProfile::DIET_CLASSIQUE) {
            return true;
        }

        if ($regime === UserFoodProfile::DIET_VEGETARIEN) {
            return $this->recettePossedeTag($recette, 'VEGETARIEN');
        }

        return false;
    }

    private function correspondAuHalal(Recette $recette, bool $estHalal): bool
    {
        if (!$estHalal) {
            return true;
        }

        return $this->recettePossedeTag($recette, 'HALAL');
    }

    private function correspondALObjectif(Recette $recette, string $objectif): bool
    {
        if ($objectif === UserFoodProfile::GOAL_CLASSIQUE) {
            return true;
        }

        if ($objectif === UserFoodProfile::GOAL_SPORTIF) {
            return $this->recettePossedeTag($recette, 'SPORTIF');
        }

        if ($objectif === UserFoodProfile::GOAL_MINCEUR) {
            return $this->recettePossedeTag($recette, 'HEALTHY');
        }

        return false;
    }

    private function compatibleAvecAllergies(Recette $recette, array $allergiesUtilisateur): bool
    {
        if (empty($allergiesUtilisateur)) {
            return true;
        }

        $allergiesRecette = $recette->getAllergies();

        if (empty($allergiesRecette)) {
            // Choix : pas d’info sur les allergies = on laisse passer
            return true;
        }

        foreach ($allergiesUtilisateur as $allergie) {
            if (in_array($allergie, $allergiesRecette, true)) {
                return false;
            }
        }

        return true;
    }

    private function recettePossedeTag(Recette $recette, string $tagRequis): bool
    {
        if (!method_exists($recette, 'getTags')) {
            return false;
        }

        foreach ($recette->getTags() as $tag) {
            if (method_exists($tag, 'getContenu') && $tag->getContenu() === $tagRequis) {
                return true;
            }
        }

        return false;
    }
}

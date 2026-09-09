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

            // tag "Végétarienne" / code VEGETARIENNE
            return $this->recettePossedeTagCode($recette, 'VEGETARIENNE');
        }

        return false;
    }

    private function correspondAuHalal(Recette $recette, bool $estHalal): bool
    {
        if (!$estHalal) {
            return true;
        }

        // Tag "Halal" → code HALAL
        return $this->recettePossedeTagCode($recette, 'HALAL');
    }

    private function correspondALObjectif(Recette $recette, string $objectif): bool
    {
        if ($objectif === UserFoodProfile::GOAL_CLASSIQUE) {
            return true;
        }

        if ($objectif === UserFoodProfile::GOAL_SPORTIF) {

            // on map SPORTIF sur le tag "Riche en protéines" → code PROTEINEE
            return $this->recettePossedeTagCode($recette, 'PROTEINEE');
        }

        if ($objectif === UserFoodProfile::GOAL_MINCEUR) {
            
            // MINCEUR , tag "Healthy" , code HEALTHY
            return $this->recettePossedeTagCode($recette, 'HEALTHY');
        }

        return false;
    }

    /** @param array<int, string> $allergiesUtilisateur */
    private function compatibleAvecAllergies(Recette $recette, array $allergiesUtilisateur): bool
    {
        if (empty($allergiesUtilisateur)) {
            return true;
        }

        $allergiesRecette = $recette->getAllergies();

        if (empty($allergiesRecette)) {
            // pas d’info allergies sur la recette , on laisse passer
            return true;
        }

        foreach ($allergiesUtilisateur as $allergie) {
            if (in_array($allergie, $allergiesRecette, true)) {
                return false;
            }
        }

        return true;
    }

    private function recettePossedeTagCode(Recette $recette, string $codeRequis): bool
    {
        foreach ($recette->getTags() as $tag) {
            $code = $tag->getCode();
            if ($code !== null && strtoupper($code) === strtoupper($codeRequis)) {
                return true;
            }
        }

        return false;
    }
}

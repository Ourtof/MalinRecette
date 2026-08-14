<?php

namespace App\Tests\Service;

use App\Entity\Recette;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\UserFoodProfile;
use App\Repository\RecetteRepository;
use App\Service\ServiceRecommandationRecettes;
use PHPUnit\Framework\TestCase;

class ServiceRecommandationRecettesTest extends TestCase
{
    private RecetteRepository $recetteRepository;
    private ServiceRecommandationRecettes $serviceRecommandationRecettes;

    protected function setUp(): void
    {
        $this->recetteRepository = $this->createMock(RecetteRepository::class);
        $this->serviceRecommandationRecettes = new ServiceRecommandationRecettes($this->recetteRepository);
    }

    public function testUtilisateurSansProfilRetourneToutesLesRecettes(): void
    {
        $utilisateur = $this->creerUtilisateur(null);
        $recettes = [
            $this->creerRecette('Recette A'),
            $this->creerRecette('Recette B'),
        ];

        $this->recetteRepository->method('findAll')->willReturn($recettes);

        $resultat = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur, 10);

        $this->assertCount(2, $resultat);
        $this->assertEqualsCanonicalizing(
            ['Recette A', 'Recette B'],
            array_map(fn (Recette $recette): string => $recette->getTitre(), $resultat)
        );
    }

    public function testFiltreRegimeVegetarien(): void
    {
        $utilisateur = $this->creerUtilisateur(
            $this->creerProfil(regime: UserFoodProfile::DIET_VEGETARIEN)
        );
        $recetteVegetarienne = $this->creerRecette('Vegetarienne', ['VEGETARIENNE']);
        $recetteClassique = $this->creerRecette('Classique');

        $this->recetteRepository->method('findAll')->willReturn([$recetteVegetarienne, $recetteClassique]);

        $resultat = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur);

        $this->assertCount(1, $resultat);
        $this->assertSame('Vegetarienne', $resultat[0]->getTitre());
    }

    public function testFiltreHalal(): void
    {
        $utilisateur = $this->creerUtilisateur(
            $this->creerProfil(halal: true)
        );
        $recetteHalal = $this->creerRecette('Halal', ['HALAL']);
        $recetteNonHalal = $this->creerRecette('Non halal');

        $this->recetteRepository->method('findAll')->willReturn([$recetteHalal, $recetteNonHalal]);

        $resultat = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur);

        $this->assertCount(1, $resultat);
        $this->assertSame('Halal', $resultat[0]->getTitre());
    }

    public function testFiltreObjectifSportif(): void
    {
        $utilisateur = $this->creerUtilisateur(
            $this->creerProfil(objectif: UserFoodProfile::GOAL_SPORTIF)
        );
        $recetteProteinee = $this->creerRecette('Proteinee', ['PROTEINEE']);
        $recetteClassique = $this->creerRecette('Classique');

        $this->recetteRepository->method('findAll')->willReturn([$recetteProteinee, $recetteClassique]);

        $resultat = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur);

        $this->assertCount(1, $resultat);
        $this->assertSame('Proteinee', $resultat[0]->getTitre());
    }

    public function testFiltreObjectifMinceur(): void
    {
        $utilisateur = $this->creerUtilisateur(
            $this->creerProfil(objectif: UserFoodProfile::GOAL_MINCEUR)
        );
        $recetteHealthy = $this->creerRecette('Healthy', ['HEALTHY']);
        $recetteClassique = $this->creerRecette('Classique');

        $this->recetteRepository->method('findAll')->willReturn([$recetteHealthy, $recetteClassique]);

        $resultat = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur);

        $this->assertCount(1, $resultat);
        $this->assertSame('Healthy', $resultat[0]->getTitre());
    }

    public function testExclutRecettesAvecAllergiesCommunes(): void
    {
        $utilisateur = $this->creerUtilisateur(
            $this->creerProfil(allergies: ['GLUTEN'])
        );
        $recetteSansAllergie = $this->creerRecette('Sans gluten declare');
        $recetteAvecGluten = $this->creerRecette('Avec gluten', allergies: ['GLUTEN']);

        $this->recetteRepository->method('findAll')->willReturn([$recetteSansAllergie, $recetteAvecGluten]);

        $resultat = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur);

        $this->assertCount(1, $resultat);
        $this->assertSame('Sans gluten declare', $resultat[0]->getTitre());
    }

    public function testRecetteSansInfoAllergiesEstConservee(): void
    {
        $utilisateur = $this->creerUtilisateur(
            $this->creerProfil(allergies: ['GLUTEN'])
        );
        $recetteSansInfo = $this->creerRecette('Allergie inconnue');

        $this->recetteRepository->method('findAll')->willReturn([$recetteSansInfo]);

        $resultat = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur);

        $this->assertCount(1, $resultat);
        $this->assertSame('Allergie inconnue', $resultat[0]->getTitre());
    }

    public function testLimiteNombreRecettes(): void
    {
        $utilisateur = $this->creerUtilisateur(null);
        $recettes = [
            $this->creerRecette('Recette 1'),
            $this->creerRecette('Recette 2'),
            $this->creerRecette('Recette 3'),
        ];

        $this->recetteRepository->method('findAll')->willReturn($recettes);

        $resultat = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur, 2);

        $this->assertCount(2, $resultat);
    }

    public function testTagCodeInsensibleALaCasse(): void
    {
        $utilisateur = $this->creerUtilisateur(
            $this->creerProfil(objectif: UserFoodProfile::GOAL_SPORTIF)
        );
        $recette = $this->creerRecette('Proteinee minuscule', ['proteinee']);

        $this->recetteRepository->method('findAll')->willReturn([$recette]);

        $resultat = $this->serviceRecommandationRecettes->recommanderPourUtilisateur($utilisateur);

        $this->assertCount(1, $resultat);
        $this->assertSame('Proteinee minuscule', $resultat[0]->getTitre());
    }

    private function creerUtilisateur(?UserFoodProfile $profil): User
    {
        $utilisateur = $this->createMock(User::class);
        $utilisateur->method('getFoodProfile')->willReturn($profil);

        return $utilisateur;
    }

    private function creerProfil(
        string $objectif = UserFoodProfile::GOAL_CLASSIQUE,
        string $regime = UserFoodProfile::DIET_CLASSIQUE,
        bool $halal = false,
        array $allergies = []
    ): UserFoodProfile {
        $profil = $this->createMock(UserFoodProfile::class);
        $profil->method('getGoalType')->willReturn($objectif);
        $profil->method('getDietType')->willReturn($regime);
        $profil->method('isHalal')->willReturn($halal);
        $profil->method('getAllergies')->willReturn($allergies);

        return $profil;
    }

    /**
     * @param list<string> $codesTag
     * @param list<string> $allergies
     */
    private function creerRecette(string $titre, array $codesTag = [], array $allergies = []): Recette
    {
        $recette = new Recette();
        $recette->setTitre($titre);

        foreach ($codesTag as $code) {
            $tag = new Tag();
            $tag
                ->setCode($code)
                ->setContenu($code)
                ->setCategorie(Tag::CATEGORIE_OBJECTIF);
            $recette->addTag($tag);
        }

        $recette->setAllergies($allergies);

        return $recette;
    }
}

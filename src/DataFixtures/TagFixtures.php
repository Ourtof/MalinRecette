<?php

namespace App\DataFixtures;

use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TagFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $tagsData = [
            // OBJECTIF
            [
                'code' => 'HEALTHY',
                'contenu' => 'Healthy',
                'categorie' => 'OBJECTIF',
            ],
            [
                'code' => 'PROTEINEE',
                'contenu' => 'Riche en protéines',
                'categorie' => 'OBJECTIF',
            ],
            [
                'code' => 'VEGETARIENNE',
                'contenu' => 'Végétarienne',
                'categorie' => 'OBJECTIF',
            ],
            [
                'code' => 'HALAL',
                'contenu' => 'Halal',
                'categorie' => 'OBJECTIF',
            ],

            // ALLERGENE
            [
                'code' => 'GLUTEN',
                'contenu' => 'Contient gluten',
                'categorie' => 'ALLERGENE',
            ],
            [
                'code' => 'LACTOSE',
                'contenu' => 'Contient lactose',
                'categorie' => 'ALLERGENE',
            ],
            [
                'code' => 'ARACHIDES',
                'contenu' => 'Contient arachides',
                'categorie' => 'ALLERGENE',
            ],
        ];

        foreach ($tagsData as $data) {
            $tag = new Tag();
            $tag->setCode($data['code']);
            $tag->setContenu($data['contenu']);
            $tag->setCategorie($data['categorie']);
            $tag->setIsActive(true);

            $manager->persist($tag);
        }

        $manager->flush();
    }
}

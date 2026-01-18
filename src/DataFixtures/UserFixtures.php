<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;
    private Generator $faker;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $manager): void
    {
        // Création de 3 utilisateurs avec Faker
        for ($i = 0; $i < 3; $i++) {
            $user = new User();
            $user->setEmail($this->faker->unique()->email());
            $user->setPseudo($this->faker->userName());
            $user->setPrenom($this->faker->firstName());
            $user->setNom($this->faker->lastName());
            $user->setAdresse($this->faker->streetAddress());
            $user->setVille($this->faker->city());
            $user->setCodePostal((int) $this->faker->postcode());
            $user->setRoles(['ROLE_USER']);

            // Hash du mot de passe "password"
            $hashedPassword = $this->hasher->hashPassword($user, 'password');
            $user->setPassword($hashedPassword);

            $manager->persist($user);
        }

        $manager->flush();
    }
}

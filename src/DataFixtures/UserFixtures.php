<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('admin@test.fr');
        $user->setPseudo('Testeur');
        $user->setPrenom('Jean');
        $user->setNom('Dupont');
        $user->setAdresse('123 rue de la Paix');
        $user->setVille('Paris');
        $user->setCodePostal(75000);
        $user->setRoles(['ROLE_USER']);

        // Hash du mot de passe "password123"
        $hashedPassword = $this->hasher->hashPassword($user, 'password');
        $user->setPassword($hashedPassword);

        $manager->persist($user);
        $manager->flush();
    }
}

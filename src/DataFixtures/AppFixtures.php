<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\Loan;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $users = [];
        $videos = [];

        // 1. Créer 1000 Utilisateurs
        for ($i = 0; $i < 1000; $i++) {
            $user = new User();
            $user->setName($faker->words(1, true));
            $user->setEmail($faker->freeEmail());
            
            $user->setPassword('password'); 
            $manager->persist($user);
            $users[] = $user;
        }

        // 2. Créer 100 Vidéos
        for ($j = 0; $j < 100; $j++) {
            $video = new Video();
            $video->setTitle($faker->words(3, true));
            $video->setYear($faker->numberBetween(1950, 2026));
            $manager->persist($video);
            $videos[] = $video;
        }

        // 3. Créer 10 000 Emprunts (Loans)
        for ($k = 0; $k < 10000; $k++) {
            $loan = new Loan();
            $checkoutDate = $faker->dateTimeBetween('-1 year', 'now');
            $loan->setStartDate($checkoutDate);
            
            // 50% de chance que la vidéo soit déjà rendue
            if ($faker->boolean(70)) {
                // La date de retour doit être APRES la date d'emprunt
                // On clone la date de checkout pour éviter de modifier l'originale (DateTime est mutable !)
                $returnDate = (clone $checkoutDate)->modify('+' . rand(1, 14) . ' days');
                $loan->setEndDate($returnDate);
            } else {
                $loan->setEndDate(null);
            }
            // On lie à un user et une vidéo au hasard
            $loan->setBorrower($faker->randomElement($users));
            $loan->setVideo($faker->randomElement($videos));

            $manager->persist($loan);

            // Pour éviter de saturer la mémoire avec 10k objets, on flush par paquets
            if ($k % 500 === 0) {
                $manager->flush();
            }
        }

        $manager->flush();
    }
}
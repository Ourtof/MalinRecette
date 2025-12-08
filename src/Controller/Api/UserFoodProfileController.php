<?php

namespace App\Controller\Api;

use App\Entity\UserFoodProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me/food-profile')]
#[IsGranted('ROLE_USER')]
class UserFoodProfileController extends AbstractController
{
    private const ALLOWED_TYPES = ['SPORTIF', 'MINCEUR', 'VEGETARIEN', 'CLASSIQUE'];

    private const ALLOWED_ALLERGIES = [
        'GLUTEN',
        'LAITAGE',
        'ARACHIDES',
        'FRUITS_A_COQUE',
        'OEUF',
        'SOJA',
        'POISSON',
        'CRUSTACES',
    ];

    #[Route('', name: 'api_food_profile_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $profile = $user->getFoodProfile();

        // Si pas de profil → on renvoie des valeurs par défaut (sans créer en base)
        if (!$profile) {
            return $this->json([
                'personType'     => 'CLASSIQUE',
                'isHalal'        => false,
                'allergies'      => [],
                'otherAllergies' => null,
            ]);
        }

        return $this->json([
            'personType'     => $profile->getType(),
            'isHalal'        => $profile->isHalal(),
            'allergies'      => $profile->getAllergies(),
            'otherAllergies' => $profile->getOtherAllergies(),
        ]);
    }

    #[Route('', name: 'api_food_profile_put', methods: ['PUT'])]
    public function upsertProfile(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'JSON invalide'], 400);
        }

        // --- Validation simple des champs attendus ---
        $errors = [];

        // personType
        if (!isset($data['personType']) || !is_string($data['personType'])) {
            $errors[] = 'personType est requis.';
        } elseif (!in_array($data['personType'], self::ALLOWED_TYPES, true)) {
            $errors[] = 'personType doit être parmi : ' . implode(', ', self::ALLOWED_TYPES);
        }

        // isHalal
        if (!array_key_exists('isHalal', $data) || !is_bool($data['isHalal'])) {
            $errors[] = 'isHalal doit être un booléen.';
        }

        // allergies
        if (!array_key_exists('allergies', $data) || !is_array($data['allergies'])) {
            $errors[] = 'allergies doit être une liste.';
        } else {
            foreach ($data['allergies'] as $allergy) {
                if (!is_string($allergy)) {
                    $errors[] = 'Chaque allergie doit être une chaîne de caractères.';
                    break;
                }
                if (!in_array($allergy, self::ALLOWED_ALLERGIES, true)) {
                    $errors[] = sprintf(
                        'Allergie "%s" invalide. Valeurs possibles : %s',
                        $allergy,
                        implode(', ', self::ALLOWED_ALLERGIES)
                    );
                    break;
                }
            }
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 400);
        }

        // --- Récupération ou création du profil ---
        $profile = $user->getFoodProfile();
        if (!$profile) {
            $profile = new UserFoodProfile();
            $profile->setUser($user);
            $user->setFoodProfile($profile);
            $em->persist($profile);
        }

        // --- Mise à jour des champs ---
        $profile->setType($data['personType']);
        $profile->setIsHalal($data['isHalal']);
        $profile->setAllergies($data['allergies']);

        // otherAllergies (optionnel)
        if (array_key_exists('otherAllergies', $data)) {
            $profile->setOtherAllergies(
                $data['otherAllergies'] !== null ? (string) $data['otherAllergies'] : null
            );
        }

        $em->flush();

        return $this->json([
            'personType'     => $profile->getType(),
            'isHalal'        => $profile->isHalal(),
            'allergies'      => $profile->getAllergies(),
            'otherAllergies' => $profile->getOtherAllergies(),
        ]);
    }
}

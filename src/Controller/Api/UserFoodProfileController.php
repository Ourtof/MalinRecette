<?php

namespace App\Controller\Api;

use App\Controller\Api\Traits\JsonRequestTrait;
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
    use JsonRequestTrait;
    private const ALLOWED_GOAL_TYPES = [
        UserFoodProfile::GOAL_CLASSIQUE,
        UserFoodProfile::GOAL_SPORTIF,
        UserFoodProfile::GOAL_MINCEUR,
    ];

    private const ALLOWED_DIET_TYPES = [
        UserFoodProfile::DIET_CLASSIQUE,
        UserFoodProfile::DIET_VEGETARIEN,
    ];

    private const ALLOWED_ALLERGIES = [
        'GLUTEN',
        'LAITAGE',
        'ARACHIDES',
    ];

    #[Route('', name: 'api_food_profile_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $profile = $user->getFoodProfile();

        // valeur par défaut si pas de profil
        if (!$profile) {
            return $this->json([
                'goalType'       => UserFoodProfile::GOAL_CLASSIQUE,
                'dietType'       => UserFoodProfile::DIET_CLASSIQUE,
                'isHalal'        => false,
                'allergies'      => [],
                'autreAllergies' => null,
            ]);
        }

        return $this->json([
            'goalType'       => $profile->getGoalType(),
            'dietType'       => $profile->getDietType(),
            'isHalal'        => $profile->isHalal(),
            'allergies'      => $profile->getAllergies(),
            'autreAllergies' => $profile->getAutreAllergies(),
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

        $errors = [];

        // goalType
        if (!isset($data['goalType']) || !is_string($data['goalType'])) {
            $errors[] = 'goalType est requis.';
        } elseif (!in_array($data['goalType'], self::ALLOWED_GOAL_TYPES, true)) {
            $errors[] = 'goalType doit être parmi : ' . implode(', ', self::ALLOWED_GOAL_TYPES);
        }

        // dietType
        if (!isset($data['dietType']) || !is_string($data['dietType'])) {
            $errors[] = 'dietType est requis.';
        } elseif (!in_array($data['dietType'], self::ALLOWED_DIET_TYPES, true)) {
            $errors[] = 'dietType doit être parmi : ' . implode(', ', self::ALLOWED_DIET_TYPES);
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

        // autreAllergies (optionnel)
        if (array_key_exists('autreAllergies', $data) && $data['autreAllergies'] !== null && !is_string($data['autreAllergies'])) {
            $errors[] = 'autreAllergies doit être une chaîne ou null.';
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 400);
        }

        // Récupération ou création du profil
        $profile = $user->getFoodProfile();
        if (!$profile) {
            $profile = new UserFoodProfile();
            $profile->setUser($user);
            $user->setFoodProfile($profile);
            $em->persist($profile);
        }

        $profile
            ->setGoalType($data['goalType'])
            ->setDietType($data['dietType'])
            ->setIsHalal($data['isHalal'])
            ->setAllergies($data['allergies']);

        if (array_key_exists('autreAllergies', $data)) {
            $profile->setAutreAllergies(
                $data['autreAllergies'] !== null ? (string) $data['autreAllergies'] : null
            );
        }

        $em->flush();

        return $this->json([
            'goalType'       => $profile->getGoalType(),
            'dietType'       => $profile->getDietType(),
            'isHalal'        => $profile->isHalal(),
            'allergies'      => $profile->getAllergies(),
            'autreAllergies' => $profile->getAutreAllergies(),
        ]);
    }
}

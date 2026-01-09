<?php

namespace App\Controller\Api\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait JsonRequestTrait
{
    /**
     * Décode et valide le JSON de la requête
     * @return array<string, mixed>|null Retourne les données décodées ou null si invalide
     */
    protected function getJsonData(Request $request): ?array
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data)) {
            return null;
        }
        
        return $data;
    }

    /**
     * retourne une réponse d'erreur JSON invalide
     */
    protected function jsonInvalidResponse(): JsonResponse
    {
        return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
    }
}

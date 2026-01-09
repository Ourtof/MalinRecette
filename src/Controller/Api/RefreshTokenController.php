<?php

namespace App\Controller\Api;

use App\Controller\Api\Traits\JsonRequestTrait;
use App\Service\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class RefreshTokenController extends AbstractController
{
    use JsonRequestTrait;

    #[Route('/refresh', name: 'api_refresh', methods: ['POST', 'OPTIONS'])]
    public function refresh(
        Request $request,
        RefreshTokenService $refreshTokenService,
        JWTTokenManagerInterface $JWTManager
    ): JsonResponse {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        $data = $this->getJsonData($request);
        if ($data === null) {
            return $this->jsonInvalidResponse();
        }

        $refreshToken = $data['refreshToken'] ?? null;

        if (!$refreshToken || !is_string($refreshToken)) {
            return $this->json(['error' => 'refreshToken requis'], 400);
        }

        // valide le refresh token et récupère l'utilisateur
        $user = $refreshTokenService->validateRefreshToken($refreshToken);

        if (!$user) {
            return $this->json(['error' => 'Refresh token invalide ou expiré'], 401);
        }

        if (!$user->isEnabled()) {
            return $this->json(['error' => 'Compte désactivé'], 403);
        }

        // génère un nouveau access token
        $newToken = $JWTManager->create($user);

        // génère un nouveau refresh token (rotation)
        $newRefreshToken = $refreshTokenService->generateRefreshToken($user);

        return $this->json([
            'token' => $newToken,
            'refreshToken' => $newRefreshToken->getToken(),
        ]);
    }
}

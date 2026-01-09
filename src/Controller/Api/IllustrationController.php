<?php

namespace App\Controller\Api;

use App\Entity\Illustration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Route('/api/illustrations', name: 'api_illustrations_')]
class IllustrationController extends AbstractController
{
    #[Route('', name: 'upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upload(
        Request $request,
        EntityManagerInterface $em,
        ParameterBagInterface $params,
    ): JsonResponse {

        // récupérer les bytes envoyés par Flutter
        $content = $request->getContent();

        if ($content === '') {
            return $this->json(['message' => 'Aucun fichier envoyé'], 400);
        }

        $size = strlen($content);
        if ($size > 5 * 1024 * 1024) {
            return $this->json(['message' => 'Fichier trop volumineux (max 5 Mo)'], 400);
        }

        // détermine le MIME à partir du contenu
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($content) ?: 'application/octet-stream';

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedMimes[$mime])) {
            return $this->json(['message' => 'Type de fichier non autorisé'], 400);
        }

        // vérifier les dimensions de l'image
        $imageInfo = @getimagesizefromstring($content);
        if ($imageInfo === false) {
            return $this->json(['message' => 'Fichier image invalide'], 400);
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $maxDimensionReject = 4500;
        $maxDimensionResize = 1920;

        // rejeter si vraiment trop grande
        if ($width > $maxDimensionReject || $height > $maxDimensionReject) {
            return $this->json([
                'message' => "Dimensions trop grandes. Maximum : {$maxDimensionReject}x{$maxDimensionReject}px"
            ], 400);
        }

        // rééchantillonnage si nécessaire
        if ($width > $maxDimensionResize || $height > $maxDimensionResize) {
            $content = $this->resizeImage($content, $width, $height, $maxDimensionResize, $mime);
            if ($content === null) {
                return $this->json(['message' => 'Erreur lors du redimensionnement de l\'image'], 500);
            }
        }

        // récupére le nom de fichier envoyé par Flutter
        $originalName = $request->headers->get('X-Filename', 'image');
        $baseName     = pathinfo($originalName, PATHINFO_FILENAME) ?: 'image';
        $extension    = pathinfo($originalName, PATHINFO_EXTENSION) ?: $allowedMimes[$mime];

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $baseName);
        $fileName = sprintf('%s_%s.%s', $safeName, uniqid(), $extension);

        // dossier de destination
        $uploadDir = $params->get('kernel.project_dir').'/public/uploads/recettes';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return $this->json(['message' => 'Impossible de créer le dossier upload'], 500);
        }

        $filePath = $uploadDir.'/'.$fileName;

        if (file_put_contents($filePath, $content) === false) {
            return $this->json(['message' => 'Erreur lors de l\'écriture du fichier'], 500);
        }

        $illustration = new Illustration();
        $illustration->setNomFichier($fileName);

        $em->persist($illustration);
        $em->flush();

        return $this->json([
            'id'         => $illustration->getId(),
            'nomFichier' => $illustration->getNomFichier(),
        ], 201);
    }

    #[Route('/{filename}', name: 'show', requirements: ['filename' => '[a-zA-Z0-9_\-\.]+'], methods: ['GET'])]
    public function show(string $filename): BinaryFileResponse
    {
        // bloquer explicitement les tentatives de path traversal
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw $this->createNotFoundException();
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/recettes';
        $path = realpath($uploadDir . '/' . $filename);

        // double vérif : le chemin résolu doit être dans le bon dossier
        if (!$path || !str_starts_with($path, realpath($uploadDir) . DIRECTORY_SEPARATOR)) {
            throw $this->createNotFoundException();
        }

        if (!file_exists($path)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path);
    }

    /**
     * Rééchantillonne une image si elle dépasse la taille maximale
     * @return string|null Contenu de l'image redimensionnée, ou null en cas d'erreur
     */
    private function resizeImage(string $imageContent, int $width, int $height, int $maxSize, string $mime): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg') || !function_exists('imagepng') || !function_exists('imagewebp')) {
            // GD non disponible, on retourne l'image originale
            return $imageContent;
        }

        // calculer les nouvelles dimensions en gardant les proportions
        $ratio = min($maxSize / $width, $maxSize / $height);
        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);

        // créer l'image depuis le contenu
        $sourceImage = @imagecreatefromstring($imageContent);
        if ($sourceImage === false) {
            return null;
        }

        // créer une nouvelle image redimensionnée
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($newImage === false) {
            imagedestroy($sourceImage);
            return null;
        }

        // préserver la transparence pour PNG et WebP
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparent);
        }

        // redimensionner
        if (!imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            return null;
        }

        // convertir en string
        ob_start();
        $success = false;

        if ($mime === 'image/jpeg') {
            $success = imagejpeg($newImage, null, 85);
        } elseif ($mime === 'image/png') {
            $success = imagepng($newImage, null, 8);
        } elseif ($mime === 'image/webp') {
            $success = imagewebp($newImage, null, 85);
        }

        imagedestroy($sourceImage);
        imagedestroy($newImage);

        if (!$success) {
            ob_end_clean();
            return null;
        }

        return ob_get_clean();
    }
}

<?php

namespace App\Controller\Api;

use App\Entity\Illustration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

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
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        // 1️⃣ Vérifier l'existence EN PREMIER
        if (!$file) {
            return $this->json(['message' => 'Aucun fichier envoyé'], 400);
        }

        if (!$file->isValid()) {
            return $this->json(['message' => 'Fichier invalide'], 400);
        }

        // 2️⃣ Ensuite valider le type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return $this->json(['message' => 'Type de fichier non autorisé'], 400);
        }

        // 3️⃣ Puis la taille
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['message' => 'Fichier trop volumineux (max 5 Mo)'], 400);
        }

        // Dossier de destination
        $uploadDir = $params->get('kernel.project_dir').'/public/uploads/recettes';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return $this->json(['message' => 'Impossible de créer le dossier upload'], 500);
        }

        // Nom de fichier safe + unique
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $originalName);
        $extension = $file->guessExtension() ?: 'bin';
        $fileName = sprintf('%s_%s.%s', $safeName, uniqid(), $extension);

        $file->move($uploadDir, $fileName);

        $illustration = new Illustration();
        $illustration->setNomFichier($fileName);

        $em->persist($illustration);
        $em->flush();

        return $this->json([
            'id' => $illustration->getId(),
            'nomFichier' => $illustration->getNomFichier(),
        ], 201);
    }

    #[Route('/{filename}', name: 'show', requirements: ['filename' => '[a-zA-Z0-9_\-\.]+'], methods: ['GET'])]
    public function show(string $filename): BinaryFileResponse
    {
        // Bloquer explicitement les tentatives de path traversal
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw $this->createNotFoundException();
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/recettes';
        $path = realpath($uploadDir . '/' . $filename);

        // Double vérification : le chemin résolu doit être dans le bon dossier
        if (!$path || !str_starts_with($path, realpath($uploadDir) . DIRECTORY_SEPARATOR)) {
            throw $this->createNotFoundException();
        }

        if (!file_exists($path)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path);
    }
}

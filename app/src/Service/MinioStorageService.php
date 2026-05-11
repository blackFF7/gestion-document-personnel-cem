<?php

declare(strict_types=1);

namespace App\Service;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service d'upload vers MinIO (compatible S3).
 * Utilisé pour stocker les documents personnels de la Caisse d'Épargne.
 */
class MinioStorageService
{
    private S3Client $client;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $bucketDocuments,
        private readonly string $bucketThumbnails,
        private readonly LoggerInterface $logger,
    ) {
        $this->client = new S3Client([
            'version'                 => 'latest',
            'region'                  => 'us-east-1',
            'endpoint'                => $this->endpoint,
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => $this->accessKey,
                'secret' => $this->secretKey,
            ],
            'http' => [
                'connect_timeout' => 5,
                'timeout'         => 30,
            ],
        ]);
    }

    /**
     * Upload un fichier dans MinIO et retourne la clé S3.
     */
    public function uploadDocument(UploadedFile $file, string $folder = 'documents'): string
    {
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $key       = sprintf(
            '%s/%s/%s.%s',
            $folder,
            (new \DateTimeImmutable())->format('Y/m'),
            bin2hex(random_bytes(16)),
            $extension,
        );

        try {
            $this->client->putObject([
                'Bucket'      => $this->bucketDocuments,
                'Key'         => $key,
                'Body'        => fopen($file->getPathname(), 'rb'),
                'ContentType' => $file->getMimeType() ?? 'application/octet-stream',
                'Metadata'    => [
                    'originalName' => $file->getClientOriginalName(),
                    'uploadedAt'   => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ],
            ]);

            $this->logger->info('Document uploadé vers MinIO', ['key' => $key]);

            return $key;
        } catch (AwsException $e) {
            $this->logger->error('Erreur upload MinIO', [
                'error' => $e->getMessage(),
                'key'   => $key,
            ]);
            throw new \RuntimeException('Impossible d\'uploader le document: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Génère une URL présignée pour accéder au document (valable 1h).
     */
    public function getPresignedUrl(string $key, int $expiresInSeconds = 3600): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucketDocuments,
            'Key'    => $key,
        ]);

        $request = $this->client->createPresignedRequest($cmd, "+{$expiresInSeconds} seconds");

        return (string) $request->getUri();
    }

    /**
     * Supprime un document de MinIO.
     */
    public function deleteDocument(string $key): void
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucketDocuments,
                'Key'    => $key,
            ]);
            $this->logger->info('Document supprimé de MinIO', ['key' => $key]);
        } catch (AwsException $e) {
            $this->logger->warning('Impossible de supprimer le document MinIO', [
                'error' => $e->getMessage(),
                'key'   => $key,
            ]);
        }
    }

    /**
     * Vérifie si MinIO est accessible (health check).
     */
    public function isAvailable(): bool
    {
        try {
            $this->client->listBuckets();
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
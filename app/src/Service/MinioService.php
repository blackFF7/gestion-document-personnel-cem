<?php

namespace App\Service;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class MinioService
{
    public function __construct(
        private S3Client $s3Client,
        private string $bucketDocuments,
        private string $bucketPreviews,
        private string $bucketPhotos,
        private string $publicEndpoint // 🔥 AJOUT IMPORTANT
    ) {}

    /**
     * Génère une URL signée temporaire (SAFE pour navigateur)
     */
    public function getSignedUrl(string $bucket, string $key, int $expiry = 3600): string
    {
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, "+{$expiry} seconds");

            // Remplacer l'host interne (minio:9000) par l'host public (localhost:9000)
            $uri = (string) $request->getUri();
            $internalHost = parse_url($this->s3Client->getEndpoint(), PHP_URL_HOST);
            $internalPort = parse_url($this->s3Client->getEndpoint(), PHP_URL_PORT);
            $publicHost   = parse_url($this->publicEndpoint, PHP_URL_HOST);
            $publicPort   = parse_url($this->publicEndpoint, PHP_URL_PORT);

            // Remplacer host:port interne par host:port public
            $find    = $internalPort ? "{$internalHost}:{$internalPort}" : $internalHost;
            $replace = $publicPort   ? "{$publicHost}:{$publicPort}"     : $publicHost;

            return str_replace($find, $replace, $uri);

        } catch (S3Exception $e) {
            throw new \RuntimeException("Impossible de générer l'URL : " . $e->getMessage());
        }
    }

    public function getPhotoUrl(string $filename): string
    {
        // Reconstruit le sous-dossier : 3 premiers chars du nom
        $subdir = substr($filename, 0, 3);
        $path = $subdir . '/' . $filename;
        return $this->getSignedUrl('photos-profil', $path);
    }

    /**
     * Vérifie si fichier existe
     */
    public function fileExists(string $bucket, string $key): bool
    {
        try {
            $this->s3Client->headObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);
            return true;
        } catch (S3Exception) {
            return false;
        }
    }

    /**
     * Supprime fichier
     */
    public function deleteFile(string $bucket, string $key): void
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);
        } catch (S3Exception $e) {
            throw new \RuntimeException("Suppression impossible : " . $e->getMessage());
        }
    }

    /**
     * Stream download
     */
    public function getStream(string $bucket, string $key)
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);
            return $result['Body']->detach();
        } catch (S3Exception $e) {
            throw new \RuntimeException("Impossible de récupérer le fichier : " . $e->getMessage());
        }
    }

    public function getBucketDocuments(): string { return $this->bucketDocuments; }
    public function getBucketPreviews(): string   { return $this->bucketPreviews; }
    public function getBucketPhotos(): string     { return $this->bucketPhotos; }
}

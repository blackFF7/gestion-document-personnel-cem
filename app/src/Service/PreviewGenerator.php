<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class PreviewGenerator
{
    // Limite 50MB
    private const MAX_SIZE = 50 * 1024 * 1024;

    // Types autorisés
    private const ALLOWED = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp',
        'doc', 'docx', 'xls', 'xlsx'
    ];

    public function __construct(
        private string $previews_directory
    ) {}

    public function generate(UploadedFile $file): ?string
    {
        // Vérification taille
        if ($file->getSize() > self::MAX_SIZE) {
            throw new \RuntimeException(
                'Fichier trop volumineux. Limite : 50 MB.'
            );
        }

        $ext = strtolower($file->getClientOriginalExtension());

        // Vérification type
        if (!in_array($ext, self::ALLOWED)) {
            throw new \RuntimeException(
                'Type de fichier non supporté. Formats acceptés : PDF, Word, Excel, Images.'
            );
        }

        $name = uniqid('preview_');
        $tempPath = $this->previews_directory . '/' . $name . '.' . $ext;

        // Déplace le fichier uploadé
        $file->move($this->previews_directory, $name . '.' . $ext);

        $previewBase = $this->previews_directory . '/' . $name;

        /* ===== IMAGES ===== */
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return '/uploads/previews/' . $name . '.' . $ext;
        }

        /* ===== PDF ===== */
        if ($ext === 'pdf') {
            // Affichage direct dans iframe — navigateur gère nativement
            return '/uploads/previews/' . $name . '.pdf';
        }

        /* ===== WORD (doc/docx) ===== */
        if (in_array($ext, ['doc', 'docx'])) {
            $htmlFile = $previewBase . '.html';

            // Tentative avec LibreOffice
            exec(sprintf(
                'libreoffice --headless --convert-to html --outdir %s %s 2>/dev/null',
                escapeshellarg($this->previews_directory),
                escapeshellarg($tempPath)
            ), $output, $code);

            // LibreOffice génère un fichier avec le même nom
            $libreOutput = $previewBase . '.html';

            if (file_exists($libreOutput)) {
                return '/uploads/previews/' . $name . '.html';
            }

            // Fallback PhpWord
            try {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($tempPath);
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
                $writer->save($htmlFile);
                return '/uploads/previews/' . $name . '.html';
            } catch (\Throwable $e) {
                // Fallback : affichage message
                file_put_contents($htmlFile,
                    '<div style="padding:20px;font-family:sans-serif;">
                        <p>⚠️ Aperçu non disponible pour ce format Word.</p>
                        <p>Le fichier sera enregistré correctement.</p>
                    </div>'
                );
                return '/uploads/previews/' . $name . '.html';
            }
        }

        /* ===== EXCEL (xls/xlsx) ===== */
        if (in_array($ext, ['xls', 'xlsx'])) {
            $htmlFile = $previewBase . '.html';

            // Tentative LibreOffice
            exec(sprintf(
                'libreoffice --headless --convert-to html --outdir %s %s 2>/dev/null',
                escapeshellarg($this->previews_directory),
                escapeshellarg($tempPath)
            ), $output, $code);

            if (file_exists($previewBase . '.html')) {
                return '/uploads/previews/' . $name . '.html';
            }

            // Fallback PhpSpreadsheet
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPath);
                $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Html');
                $writer->save($htmlFile);
                return '/uploads/previews/' . $name . '.html';
            } catch (\Throwable $e) {
                file_put_contents($htmlFile,
                    '<div style="padding:20px;font-family:sans-serif;">
                        <p>⚠️ Aperçu non disponible pour ce fichier Excel.</p>
                        <p>Le fichier sera enregistré correctement.</p>
                    </div>'
                );
                return '/uploads/previews/' . $name . '.html';
            }
        }

        return null;
    }
}
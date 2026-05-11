<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Personnel;

/**
 * Service d'audit pour les actions sur les documents.
 *
 * Chaque action (CREATE, UPDATE, DELETE, STATUS_CHANGE) est tracée.
 * À connecter à une entité AuditLog ou à un système externe (ELK, etc.).
 */
class DocumentAuditService
{
    public function log(Document $document, string $action, Personnel $actor, array $extra = []): void
    {
        $entry = [
            'timestamp'  => (new \DateTime())->format('Y-m-d H:i:s'),
            'document'   => (string) $document->getId(),
            'reference'  => $document->getReference(),
            'action'     => $action,
            'actor'      => $actor->getFullName(),
            'actor_id'   => (string) $actor->getId(),
            'actor_role' => implode(',', $actor->getRoles()),
        ];

        if (!empty($extra)) {
            $entry = array_merge($entry, $extra);
        }

        // TODO : persister dans une table audit_log ou envoyer dans ELK/Sentry
        // Exemple : $this->auditLogRepo->create($entry);
        error_log('[AUDIT] ' . json_encode($entry, JSON_UNESCAPED_UNICODE));
    }
}

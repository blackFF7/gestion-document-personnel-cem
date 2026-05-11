<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(columns: ['entity_type'], name: 'idx_audit_entity_type')]
#[ORM\Index(columns: ['entity_id'], name: 'idx_audit_entity_id')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_created_at')]
#[ORM\Index(columns: ['action'], name: 'idx_audit_action')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    /**
     * Type d'entité concernée : 'personnel' | 'document'
     */
    #[ORM\Column(length: 50)]
    private string $entityType;

    /**
     * UUID de l'entité concernée (Personnel ou Document)
     */
    #[ORM\Column(length: 36)]
    private string $entityId;

    /**
     * Label lisible de l'entité (ex : "RAKOTO Jean", "REF-001")
     */
    #[ORM\Column(length: 255)]
    private string $entityLabel;

    /**
     * Action réalisée : 'create' | 'update' | 'delete' | 'status_change'
     *                   | 'login' | 'logout' | 'view'
     */
    #[ORM\Column(length: 50)]
    private string $action;

    /**
     * Description lisible de l'action
     */
    #[ORM\Column(length: 500)]
    private string $description;

    /**
     * Données avant modification (JSON)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldData = null;

    /**
     * Données après modification (JSON)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newData = null;

    /**
     * Champs modifiés (liste des clés changées)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $changedFields = null;

    /**
     * Auteur de l'action (Personnel connecté)
     */
    #[ORM\ManyToOne(targetEntity: Personnel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $author = null;

    /**
     * Adresse IP
     */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    /**
     * User-Agent navigateur
     */
    #[ORM\Column(length: 300, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id        = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?Uuid { return $this->id; }

    public function getEntityType(): string { return $this->entityType; }
    public function setEntityType(string $entityType): static { $this->entityType = $entityType; return $this; }

    public function getEntityId(): string { return $this->entityId; }
    public function setEntityId(string $entityId): static { $this->entityId = $entityId; return $this; }

    public function getEntityLabel(): string { return $this->entityLabel; }
    public function setEntityLabel(string $entityLabel): static { $this->entityLabel = $entityLabel; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getOldData(): ?array { return $this->oldData; }
    public function setOldData(?array $oldData): static { $this->oldData = $oldData; return $this; }

    public function getNewData(): ?array { return $this->newData; }
    public function setNewData(?array $newData): static { $this->newData = $newData; return $this; }

    public function getChangedFields(): ?array { return $this->changedFields; }
    public function setChangedFields(?array $changedFields): static { $this->changedFields = $changedFields; return $this; }

    public function getAuthor(): ?Personnel { return $this->author; }
    public function setAuthor(?Personnel $author): static { $this->author = $author; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): static { $this->ipAddress = $ipAddress; return $this; }

    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): static { $this->userAgent = $userAgent; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getActionBadgeClass(): string
    {
        return match ($this->action) {
            'create'        => 'audit-badge-create',
            'update'        => 'audit-badge-update',
            'delete'        => 'audit-badge-delete',
            'status_change' => 'audit-badge-status',
            'login'         => 'audit-badge-login',
            'logout'        => 'audit-badge-logout',
            default         => 'audit-badge-default',
        };
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'create'        => 'Création',
            'update'        => 'Modification',
            'delete'        => 'Suppression',
            'status_change' => 'Changement de statut',
            'login'         => 'Connexion',
            'logout'        => 'Déconnexion',
            'view'          => 'Consultation',
            default         => ucfirst($this->action),
        };
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511190655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id VARCHAR(36) NOT NULL, entity_label VARCHAR(255) NOT NULL, action VARCHAR(50) NOT NULL, description VARCHAR(500) NOT NULL, old_data JSON DEFAULT NULL, new_data JSON DEFAULT NULL, changed_fields JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(300) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, author_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F6E1C0F5F675F31B ON audit_log (author_id)');
        $this->addSql('CREATE INDEX idx_audit_entity_type ON audit_log (entity_type)');
        $this->addSql('CREATE INDEX idx_audit_entity_id ON audit_log (entity_id)');
        $this->addSql('CREATE INDEX idx_audit_created_at ON audit_log (created_at)');
        $this->addSql('CREATE INDEX idx_audit_action ON audit_log (action)');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5F675F31B FOREIGN KEY (author_id) REFERENCES personnel (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE personnel ALTER date_naiss_ag DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F5F675F31B');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('ALTER TABLE personnel ALTER date_naiss_ag SET NOT NULL');
    }
}

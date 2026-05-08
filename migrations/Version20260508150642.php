<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260508150642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorites (profile_id VARCHAR(36) NOT NULL, stream_id VARCHAR(255) NOT NULL, stream_type VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (profile_id, stream_id))');
        $this->addSql('CREATE TABLE watch_progress (profile_id VARCHAR(36) NOT NULL, stream_id VARCHAR(255) NOT NULL, stream_type VARCHAR(20) NOT NULL, timestamp_seconds INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (profile_id, stream_id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE favorites');
        $this->addSql('DROP TABLE watch_progress');
    }
}

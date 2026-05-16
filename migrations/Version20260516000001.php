<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add episode metadata columns to watch_progress for cross-device Continue Watching';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE watch_progress ADD COLUMN series_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_progress ADD COLUMN series_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_progress ADD COLUMN season INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_progress ADD COLUMN episode_num INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_progress ADD COLUMN episode_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_progress ADD COLUMN cover VARCHAR(1000) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE watch_progress DROP COLUMN series_id');
        $this->addSql('ALTER TABLE watch_progress DROP COLUMN series_title');
        $this->addSql('ALTER TABLE watch_progress DROP COLUMN season');
        $this->addSql('ALTER TABLE watch_progress DROP COLUMN episode_num');
        $this->addSql('ALTER TABLE watch_progress DROP COLUMN episode_title');
        $this->addSql('ALTER TABLE watch_progress DROP COLUMN cover');
    }
}

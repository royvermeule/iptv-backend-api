<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable pin column to profiles table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profiles ADD COLUMN pin INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profiles DROP COLUMN pin');
    }
}

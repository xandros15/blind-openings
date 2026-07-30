<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730144614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add column for available paths';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('theme')->modifyColumn('paths', [
            'notnull' => false,
        ]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('theme')->modifyColumn('paths', [
            'notnull' => true,
        ]);
    }
}

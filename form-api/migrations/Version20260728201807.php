<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728201807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused columns';
    }

    public function up(Schema $schema): void
    {
        $anime = $schema->getTable('anime');
        $anime->dropColumn('url');
        $anime->dropColumn('image');
        $anime->dropColumn('name');

    }

    public function down(Schema $schema): void
    {
        $anime = $schema->createTable('anime');
        $anime->addColumn('url', 'string', [
            'length' => 128,
        ]);
        $anime->addColumn('image', 'string', [
            'length' => 128,
            'notnull' => false,
        ]);
        $anime->addColumn('name', 'string', [
            'length' => 1024,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728195905 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unique constraint from teams';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('team_account');
        $table->dropIndex('UNIQ_824F27938FC28A7DD2B0F9ACE19D9AD2');

    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('team_account');
        $table->addUniqueIndex(['team_name', 'account_name', 'service'], 'UNIQ_824F27938FC28A7DD2B0F9ACE19D9AD2');
    }
}

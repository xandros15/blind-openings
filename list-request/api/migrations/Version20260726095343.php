<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726095343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Init';
    }

    public function up(Schema $schema): void
    {
        $teamAccount = $schema->createTable('team_account');
        $teamAccount->addColumn('id', 'guid', [
            'length' => 36,
        ]);
        $teamAccount->addColumn('team_id', 'guid', [
            'length' => 36,
        ]);
        $teamAccount->addColumn('team_name', 'string', [
            'length' => 64,
        ]);
        $teamAccount->addColumn('account_name', 'string', [
            'length' => 64,
        ]);
        $teamAccount->addColumn('service', 'string', [
            'length' => 32,
        ]);
        $teamAccount->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                                ->setUnquotedColumnNames('id')
                                ->create()
        );
        $teamAccount->addIndex(['team_name'], 'idx_team_account_team_name');
        $teamAccount->addIndex(['team_id'], 'idx_team_account_team_id');
        $teamAccount->addUniqueConstraint(['team_name', 'account_name', 'service']);
        $anime = $schema->createTable('anime');
        $anime->addColumn('team_account_id', 'guid', [
            'length' => 36,
        ]);
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
        $anime->addColumn('external_id', 'integer');
        $anime->addIndex(['team_account_id'], 'idx_anime_team_account_id');
        $anime->addForeignKeyConstraint(
            'team_account',
            ['team_account_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_anime_team_account'
        );

    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('anime');
        $schema->dropTable('team_account');
    }
}

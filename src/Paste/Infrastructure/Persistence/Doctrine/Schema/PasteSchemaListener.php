<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine\Schema;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class PasteSchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $log = $args->getSchema()->createTable('paste_deletion_log');

        $log->addColumn('id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
        $log->addColumn('paste_id', Types::STRING, [
            'length' => 22,
            'fixed' => true,
            'platformOptions' => ['collation' => 'utf8mb4_bin'],
        ]);
        $log->addColumn('deleted_by_user_id', Types::STRING, ['length' => 36, 'fixed' => true, 'notnull' => false]);
        $log->addColumn('reason', Types::STRING, ['length' => 16]);
        $log->addColumn('deleted_at', Types::DATETIME_IMMUTABLE);

        $log->setPrimaryKey(['id']);
        $log->addUniqueIndex(['paste_id'], 'uniq_deletion_log_paste');
        $log->addIndex(['deleted_at'], 'idx_deletion_log_deleted_at');
        $log->addIndex(['deleted_by_user_id'], 'idx_deletion_log_actor');
    }
}
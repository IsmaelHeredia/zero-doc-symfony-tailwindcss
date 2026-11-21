<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Schema;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class OrganizationSchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $pivot = $args->getSchema()->createTable('paste_tag');

        $pivot->addColumn('tag_id', Types::STRING, ['length' => 36, 'fixed' => true]);
        $pivot->addColumn('paste_id', Types::STRING, [
            'length' => 22,
            'fixed' => true,
            'platformOptions' => ['collation' => 'utf8mb4_bin'],
        ]);

        $pivot->setPrimaryKey(['tag_id', 'paste_id']);
        $pivot->addIndex(['paste_id'], 'idx_paste_tag_paste');
        $pivot->addForeignKeyConstraint('tag', ['tag_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_paste_tag_tag');
    }
}
<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Schema;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class IdentitySchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();

        $permission = $schema->createTable('permission');
        $permission->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $permission->addColumn('code', Types::STRING, ['length' => 64]);
        $permission->setPrimaryKey(['id']);
        $permission->addUniqueIndex(['code'], 'uniq_permission_code');

        $userRole = $schema->createTable('user_role');
        $userRole->addColumn('user_id', Types::STRING, ['length' => 36, 'fixed' => true]);
        $userRole->addColumn('role_id', Types::STRING, ['length' => 36, 'fixed' => true]);
        $userRole->setPrimaryKey(['user_id', 'role_id']);
        $userRole->addForeignKeyConstraint('user', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_user_role_user');
        $userRole->addForeignKeyConstraint('role', ['role_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_user_role_role');

        $rolePermission = $schema->createTable('role_permission');
        $rolePermission->addColumn('role_id', Types::STRING, ['length' => 36, 'fixed' => true]);
        $rolePermission->addColumn('permission_id', Types::INTEGER, ['unsigned' => true]);
        $rolePermission->setPrimaryKey(['role_id', 'permission_id']);
        $rolePermission->addForeignKeyConstraint('role', ['role_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_role_permission_role');
        $rolePermission->addForeignKeyConstraint('permission', ['permission_id'], ['id'], [], 'fk_role_permission_permission');

        $audit = $schema->createTable('admin_audit_log');
        $audit->addColumn('id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
        $audit->addColumn('actor_id', Types::STRING, ['length' => 36, 'fixed' => true]);
        $audit->addColumn('action', Types::STRING, ['length' => 24]);
        $audit->addColumn('target_user_id', Types::STRING, ['length' => 36, 'fixed' => true, 'notnull' => false]);
        $audit->addColumn('role_name', Types::STRING, ['length' => 32]);
        $audit->addColumn('permission_code', Types::STRING, ['length' => 64, 'notnull' => false]);
        $audit->addColumn('occurred_at', Types::DATETIME_IMMUTABLE);
        $audit->setPrimaryKey(['id']);
        $audit->addIndex(['occurred_at'], 'idx_admin_audit_occurred_at');
        $audit->addIndex(['actor_id'], 'idx_admin_audit_actor');
        $audit->addIndex(['target_user_id'], 'idx_admin_audit_target');

    }
}
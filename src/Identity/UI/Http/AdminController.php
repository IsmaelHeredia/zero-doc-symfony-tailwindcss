<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Admin\AssignRole\AdminAssignRoleCommand;
use App\Identity\Application\Admin\GrantPermission\GrantPermissionCommand;
use App\Identity\Application\Admin\Query\ListRoles;
use App\Identity\Application\Admin\Query\ListUsers;
use App\Identity\Application\Admin\Query\RoleSummary;
use App\Identity\Application\Admin\Query\UserSummary;
use App\Identity\Application\Admin\RevokePermission\RevokePermissionCommand;
use App\Identity\Application\Admin\RevokeRole\AdminRevokeRoleCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Identity\Application\Admin\Query\ListAdminAudit;
use App\Identity\Application\Admin\Query\AdminAuditEntry;

#[AsController]
final class AdminController
{
    use HandleTrait;

    private const NO_STORE = ['Cache-Control' => 'no-store'];
    private const USER_ID = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';
    private const ROLE = '[a-z][a-z0-9_]{1,31}';
    private const PERMISSION = '[a-z]+(?:\.[a-z_]+)+';

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly ListUsers $listUsers,
        private readonly ListRoles $listRoles,
        private readonly ListAdminAudit $listAudit
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/admin/users', name: 'admin_users', methods: ['GET'])]
    public function users(): JsonResponse
    {
        $listing = ($this->listUsers)();

        return new JsonResponse([
            'users' => array_map(static fn (UserSummary $u): array => [
                'id' => $u->id,
                'email' => $u->email,
                'roles' => $u->roles,
                'createdAt' => $u->createdAt,
            ], $listing->users),
            'availableRoles' => $listing->availableRoles,
            'truncated' => $listing->truncated,
        ], 200, self::NO_STORE);
    }

    #[Route('/admin/users/{userId}/roles/{role}', name: 'admin_user_role_assign', requirements: ['userId' => self::USER_ID, 'role' => self::ROLE], methods: ['PUT'])]
    public function assignRole(string $userId, string $role): Response
    {
        $this->handle(new AdminAssignRoleCommand($userId, $role));

        return new Response(null, 204, self::NO_STORE);
    }

    #[Route('/admin/users/{userId}/roles/{role}', name: 'admin_user_role_revoke', requirements: ['userId' => self::USER_ID, 'role' => self::ROLE], methods: ['DELETE'])]
    public function revokeRole(string $userId, string $role): Response
    {
        $this->handle(new AdminRevokeRoleCommand($userId, $role));

        return new Response(null, 204, self::NO_STORE);
    }

    #[Route('/admin/roles', name: 'admin_roles', methods: ['GET'])]
    public function roles(): JsonResponse
    {
        $view = ($this->listRoles)();

        return new JsonResponse([
            'roles' => array_map(static fn (RoleSummary $r): array => ['name' => $r->name, 'permissions' => $r->permissions], $view->roles),
            'catalog' => $view->catalog,
            'protectedAdminPermissions' => $view->protectedAdminPermissions,
        ], 200, self::NO_STORE);
    }

    #[Route('/admin/roles/{role}/permissions/{permission}', name: 'admin_role_permission_grant', requirements: ['role' => self::ROLE, 'permission' => self::PERMISSION], methods: ['PUT'])]
    public function grant(string $role, string $permission): Response
    {
        $this->handle(new GrantPermissionCommand($role, $permission));

        return new Response(null, 204, self::NO_STORE);
    }

    #[Route('/admin/roles/{role}/permissions/{permission}', name: 'admin_role_permission_revoke', requirements: ['role' => self::ROLE, 'permission' => self::PERMISSION], methods: ['DELETE'])]
    public function revoke(string $role, string $permission): Response
    {
        $this->handle(new RevokePermissionCommand($role, $permission));

        return new Response(null, 204, self::NO_STORE);
    }

    #[Route('/admin/audit/actions', name: 'admin_audit_actions', methods: ['GET'])]
    public function auditActions(): JsonResponse
    {
        return new JsonResponse(['actions' => array_map(static fn (AdminAuditEntry $e): array => [
            'occurredAt' => $e->occurredAt,
            'action' => $e->action,
            'actorId' => $e->actorId,
            'targetUserId' => $e->targetUserId,
            'roleName' => $e->roleName,
            'permissionCode' => $e->permissionCode,
        ], ($this->listAudit)())], 200, self::NO_STORE);
    }

}
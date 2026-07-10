<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Projection;

use JsonSerializable;
use Vaened\Sentinel\Authorization;
use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\SubjectPermission;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\SubjectPermissionState;

final readonly class SubjectAuthorizationProjection implements JsonSerializable
{
    public function __construct(
        private Authorizations     $roles,
        private SubjectPermissions $permissions,
    )
    {
    }

    public static function fromArray(array $value): self|null
    {
        $roles       = $value['roles'] ?? null;
        $permissions = $value['permissions'] ?? null;

        if (!is_array($roles) || !array_is_list($roles) || !is_array($permissions)) {
            return null;
        }

        $projectedRoles = [];

        foreach ($roles as $role) {
            if (!is_string($role)) {
                return null;
            }

            $projectedRoles[] = new ProjectionAuthorization($role);
        }

        $projectedPermissions = [];

        foreach ($permissions as $code => $state) {
            if (!is_string($code) || !is_int($state)) {
                return null;
            }

            $state = SubjectPermissionState::tryFrom($state);

            if ($state === null) {
                return null;
            }

            $projectedPermissions[] = new ProjectionSubjectPermission($code, $state);
        }

        return new self(new Authorizations($projectedRoles), new SubjectPermissions($projectedPermissions));
    }

    public function roles(): Authorizations
    {
        return $this->roles;
    }

    public function permissions(): SubjectPermissions
    {
        return $this->permissions;
    }

    public function rolesOf(array $codes): Authorizations
    {
        if ($codes === []) {
            return new Authorizations([]);
        }

        return new Authorizations(array_values(array_filter(
            $this->roles->values(),
            static fn(Authorization $role): bool => in_array($role->code(), $codes, true),
        )));
    }

    public function permissionsOf(array $codes): SubjectPermissions
    {
        if ($codes === []) {
            return new SubjectPermissions([]);
        }

        return new SubjectPermissions(array_values(array_filter(
            $this->permissions->values(),
            static fn(SubjectPermission $permission): bool => in_array($permission->code(), $codes, true),
        )));
    }

    public function integrate(Authorization $role, array $grantCodes): self
    {
        if ($this->roles->hasCode($role->code())) {
            return $this;
        }

        $roles       = new Authorizations([...$this->roles->values(), new ProjectionAuthorization($role->code())]);
        $permissions = $this->permissions->values();
        $known       = $this->permissions->codes();

        foreach ($grantCodes as $code) {
            if (!in_array($code, $known, true)) {
                $permissions[] = new ProjectionSubjectPermission($code, SubjectPermissionState::Inherited);
                $known[]       = $code;
            }
        }

        return new self($roles, new SubjectPermissions($permissions));
    }

    public function override(SubjectPermission $permission): self
    {
        $permissions = array_values(array_filter(
            $this->permissions->values(),
            static fn(SubjectPermission $current): bool => $current->code() !== $permission->code(),
        ));

        $permissions[] = new ProjectionSubjectPermission($permission->code(), $permission->state());

        return new self($this->roles, new SubjectPermissions($permissions));
    }

    /**
     * @return array{roles: list<string>, permissions: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'roles'       => $this->roles->codes(),
            'permissions' => array_column(
                array_map(
                    static fn(SubjectPermission $permission): array => [
                        'code'  => $permission->code(),
                        'state' => $permission->state()->value,
                    ],
                    $this->permissions->values(),
                ),
                'state',
                'code',
            ),
        ];
    }

    /**
     * @return array{roles: list<string>, permissions: array<string, int>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

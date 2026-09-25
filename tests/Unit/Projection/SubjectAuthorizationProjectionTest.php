<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit\Projection;

use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Projection\ProjectionAuthorization;
use Vaened\Sentinel\Projection\ProjectionSubjectPermission;
use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\SubjectPermissionState;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\TestCase;

final class SubjectAuthorizationProjectionTest extends TestCase
{
    public function test_roles_and_permissions_return_the_original_values(): void
    {
        $projection = new SubjectAuthorizationProjection(
            new Authorizations([new ProjectionAuthorization('admin'), new ProjectionAuthorization('editor')]),
            new SubjectPermissions([
                new ProjectionSubjectPermission('posts.edit', SubjectPermissionState::Direct),
                new ProjectionSubjectPermission('posts.delete', SubjectPermissionState::Denied),
            ]),
        );

        self::assertSame(['admin', 'editor'], $projection->roles()->codes());
        self::assertSame(SubjectPermissionState::Direct, $projection->permissions()->find('posts.edit')?->state());
        self::assertSame(SubjectPermissionState::Denied, $projection->permissions()->find('posts.delete')?->state());
    }

    public function test_to_array_returns_the_expected_structure(): void
    {
        $projection = $this->projection(
            [new ProjectionAuthorization('admin')],
            [new ProjectionSubjectPermission('posts.edit', SubjectPermissionState::Direct)],
        );

        self::assertSame([
            'roles'       => ['admin'],
            'permissions' => ['posts.edit' => 1],
        ], $projection->toArray());
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $projection = $this->projection(
            [new ProjectionAuthorization('admin')],
            [
                new ProjectionSubjectPermission('posts.edit', SubjectPermissionState::Direct),
                new ProjectionSubjectPermission('posts.delete', SubjectPermissionState::Denied),
            ],
        );

        self::assertSame($projection->toArray(), $projection->jsonSerialize());
    }

    public function test_from_array_returns_null_for_an_invalid_projection(): void
    {
        self::assertNull(SubjectAuthorizationProjection::fromArray([
            'roles'       => ['admin'],
            'permissions' => ['posts.edit' => 'invalid'],
        ]));
    }

    public function test_integrate_records_that_a_denied_permission_is_also_granted_by_a_role(): void
    {
        $projection = $this->projection(
            permissions: [new ProjectionSubjectPermission('documents.annul', SubjectPermissionState::Denied)],
        );
        $admin      = new TestRole(10, 'admin', 'Administrator');

        $integrated = $projection->integrate($admin, ['documents.annul', 'users.read']);

        self::assertSame(['admin'], $integrated->roles()->codes());
        self::assertSame(SubjectPermissionState::DeniedInherited, $integrated->permissions()->find('documents.annul')?->state());
        self::assertSame(SubjectPermissionState::Inherited, $integrated->permissions()->find('users.read')?->state());
    }

    public function test_integrate_is_a_noop_when_the_role_is_already_present(): void
    {
        $projection = $this->projection(
            [new ProjectionAuthorization('admin')],
            [new ProjectionSubjectPermission('users.read', SubjectPermissionState::Direct)],
        );
        $admin      = new TestRole(10, 'admin', 'Administrator');

        self::assertSame($projection, $projection->integrate($admin, ['users.read']));
    }

    public function test_override_preserves_an_inherited_grant_when_the_subject_permission_changes(): void
    {
        $projection = $this->projection(
            permissions: [new ProjectionSubjectPermission('users.read', SubjectPermissionState::Inherited)],
        );

        $overridden = $projection->override(
            new ProjectionSubjectPermission('users.read', SubjectPermissionState::Denied),
        );

        self::assertSame(SubjectPermissionState::DeniedInherited, $overridden->permissions()->find('users.read')?->state());
    }

    private function projection(array $roles = [], array $permissions = []): SubjectAuthorizationProjection
    {
        return new SubjectAuthorizationProjection(
            new Authorizations($roles),
            new SubjectPermissions($permissions),
        );
    }
}

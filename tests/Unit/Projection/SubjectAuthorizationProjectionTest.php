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

use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
use Vaened\Sentinel\SubjectPermissionState;
use Vaened\Sentinel\Tests\TestCase;

final class SubjectAuthorizationProjectionTest extends TestCase
{
    public function test_roles_and_permissions_return_the_original_values(): void
    {
        $projection = new SubjectAuthorizationProjection(
            ['admin', 'editor'],
            ['posts.edit' => SubjectPermissionState::Direct->value, 'posts.delete' => SubjectPermissionState::Denied->value],
        );

        self::assertSame(['admin', 'editor'], $projection->roles());
        self::assertSame(['posts.edit' => 1, 'posts.delete' => 0], $projection->permissions());
    }

    public function test_to_array_returns_the_expected_structure(): void
    {
        $projection = new SubjectAuthorizationProjection(
            ['admin'],
            ['posts.edit' => SubjectPermissionState::Direct->value],
        );

        self::assertSame([
            'roles' => ['admin'],
            'permissions' => ['posts.edit' => 1],
        ], $projection->toArray());
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $projection = new SubjectAuthorizationProjection(
            ['admin'],
            ['posts.edit' => SubjectPermissionState::Direct->value, 'posts.delete' => SubjectPermissionState::Denied->value],
        );

        self::assertSame($projection->toArray(), $projection->jsonSerialize());
    }
}

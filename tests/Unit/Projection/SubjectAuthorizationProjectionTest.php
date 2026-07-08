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
use Vaened\Sentinel\Tests\TestCase;

final class SubjectAuthorizationProjectionTest extends TestCase
{
    public function test_roles_and_permissions_return_the_original_values(): void
    {
        $projection = new SubjectAuthorizationProjection(
            ['admin', 'editor'],
            ['posts.edit' => true, 'posts.delete' => false],
        );

        self::assertSame(['admin', 'editor'], $projection->roles());
        self::assertSame(['posts.edit' => true, 'posts.delete' => false], $projection->permissions());
    }

    public function test_to_array_returns_the_expected_structure(): void
    {
        $projection = new SubjectAuthorizationProjection(
            ['admin'],
            ['posts.edit' => true],
        );

        self::assertSame([
            'roles' => ['admin'],
            'permissions' => ['posts.edit' => true],
        ], $projection->toArray());
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $projection = new SubjectAuthorizationProjection(
            ['admin'],
            ['posts.edit' => true, 'posts.delete' => false],
        );

        self::assertSame($projection->toArray(), $projection->jsonSerialize());
    }
}

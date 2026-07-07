<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit;

use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Tests\Runtime\TestPermission;

final class PermissionsCollectionTest extends AuthorizationsCollectionTestCase
{
    protected function create(int $id, string $code): TestPermission
    {
        return new TestPermission($id, $code, ucfirst($code));
    }

    protected function collect(array $entities): Permissions
    {
        return new Permissions($entities);
    }

    protected function expectedType(): string
    {
        return Permission::class;
    }
}

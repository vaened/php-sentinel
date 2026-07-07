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

use Vaened\Sentinel\Role;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Tests\Runtime\TestRole;

final class RolesCollectionTest extends AuthorizationsCollectionTestCase
{
    protected function create(int $id, string $code): TestRole
    {
        return new TestRole($id, $code, ucfirst($code));
    }

    protected function collect(array $entities): Roles
    {
        return new Roles($entities);
    }

    protected function expectedType(): string
    {
        return Role::class;
    }
}

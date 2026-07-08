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

use Vaened\Sentinel\SubjectPermission;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\TestSubjectPermission;

final class SubjectPermissionsCollectionTest extends AuthorizationsCollectionTestCase
{
    protected function create(int $id, string $code): TestSubjectPermission
    {
        return new TestSubjectPermission($id, $code);
    }

    protected function collect(array $entities): SubjectPermissions
    {
        return new SubjectPermissions($entities);
    }

    protected function expectedType(): string
    {
        return SubjectPermission::class;
    }
}

<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Repositories;

use Vaened\Sentinel\Subject;
use Vaened\Sentinel\SubjectPermission;
use Vaened\Sentinel\SubjectPermissions;

interface SubjectPermissionRepository
{
    public function lookup(Subject $subject, string ...$codes): SubjectPermissions;

    public function exists(int|string $permissionId): bool;

    public function allOf(Subject $subject): SubjectPermissions;

    public function create(Subject $subject, SubjectPermission ...$permissions): void;

    public function update(Subject $subject, SubjectPermission ...$permissions): void;

    public function remove(Subject $subject, SubjectPermission ...$permissions): void;
}

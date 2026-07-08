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

use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\SubjectPermissions;

interface SubjectPermissionRepository
{
    public function lookup(Subject $subject, string ...$codes): SubjectPermissions;

    public function allOf(Subject $subject): SubjectPermissions;

    public function exists(int|string $permissionId): bool;

    public function create(Subject $subject, SubjectPermissionSnapshot ...$permissions): void;

    public function update(Subject $subject, SubjectPermissionSnapshot ...$permissions): void;

    public function remove(Subject $subject, SubjectPermissionSnapshot ...$permissions): void;
}

<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Authorization;

use Vaened\Sentinel\Role;
use Vaened\Sentinel\Subject;

/**
 * Provides effective permission entries for permission evaluation.
 *
 * Implementations must only consider the requested codes and must never return entries for codes
 * outside that requested set.
 *
 * Returned entries must express the effective result for each matching code:
 * - Subject permissions are resolved from direct subject permissions plus permissions inherited
 *   from the subject's roles;
 * - A subject-level denial must prevail over any granted permission inherited from roles;
 * - Role permissions are resolved only from the role's direct permission assignments;
 * - Role permissions do not support explicit denials.
 */
interface PermissionEntryProvider
{
    public function forSubject(Subject $subject, string ...$permissions): PermissionEntries;

    public function forRole(Role $role, string ...$permissions): PermissionEntries;
}

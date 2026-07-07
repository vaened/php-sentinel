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

use Vaened\Sentinel\Subject;

/**
 * Provides effective role entries for role evaluation.
 *
 * Implementations must only consider the requested codes and must return only the roles directly
 * assigned to the subject that match those codes.
 */
interface RoleEntryProvider
{
    public function forSubject(Subject $subject, string ...$roles): RoleEntries;
}

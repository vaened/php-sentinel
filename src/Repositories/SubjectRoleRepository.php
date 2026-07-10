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

use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Subject;

interface SubjectRoleRepository
{
    public function lookup(Subject $subject, string ...$codes): Authorizations;

    public function grants(Subject $subject, ?array $codes = null): Authorizations;

    public function allOf(Subject $subject): Authorizations;

    public function exists(int|string $roleId): bool;

    public function create(Subject $subject, Role ...$roles): void;

    public function remove(Subject $subject, Role ...$roles): void;
}

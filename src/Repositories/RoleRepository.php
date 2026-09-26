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

use Vaened\Sentinel\Role;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Subject;

interface RoleRepository
{
    public function lookup(Subject|null $scope, string ...$codes): Roles;

    public function match(string ...$codes): Roles;

    public function exists(int|string $id): bool;

    public function create(
        string       $code,
        string       $name,
        string|null  $description = null,
        Subject|null $scope = null,
    ): Role;

    public function update(int|string $id, string $name, string|null $description = null): void;

    public function remove(int|string $id): void;
}

<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Directory;

use Vaened\Sentinel\Errors\PermissionAlreadyExists;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Repositories\PermissionRepository;

final readonly class PermissionCreator
{
    public function __construct(
        protected PermissionRepository $permissions,
    ) {
    }

    public function create(string $code, string $name, string|null $description = null): Permission
    {
        if (!$this->permissions->lookup($code)->isEmpty()) {
            throw PermissionAlreadyExists::fromCode($code);
        }

        return $this->permissions->create($code, $name, $description);
    }
}

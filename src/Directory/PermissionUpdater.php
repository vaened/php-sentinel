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

use Vaened\Sentinel\Errors\PermissionNotFound;
use Vaened\Sentinel\Repositories\PermissionRepository;

final readonly class PermissionUpdater
{
    public function __construct(
        protected PermissionRepository $permissions,
    ) {
    }

    public function update(int|string $id, string $name, string|null $description = null): void
    {
        if (!$this->permissions->exists($id)) {
            throw PermissionNotFound::fromId($id);
        }

        $this->permissions->update($id, $name, $description);
    }
}

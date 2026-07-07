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

use Vaened\Sentinel\Errors\RoleNotFound;
use Vaened\Sentinel\Repositories\RoleRepository;

final readonly class RoleUpdater
{
    public function __construct(
        protected RoleRepository $roles,
    ) {
    }

    public function update(int|string $id, string $name, string|null $description = null): void
    {
        if (!$this->roles->exists($id)) {
            throw RoleNotFound::fromId($id);
        }

        $this->roles->update($id, $name, $description);
    }
}

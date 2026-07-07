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

use Vaened\Sentinel\Errors\RoleAlreadyExists;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Repositories\RoleRepository;

final readonly class RoleCreator
{
    public function __construct(
        protected RoleRepository $roles,
    ) {
    }

    public function create(string $code, string $name, string|null $description = null): Role
    {
        if (!$this->roles->lookup($code)->isEmpty()) {
            throw RoleAlreadyExists::fromCode($code);
        }

        return $this->roles->create($code, $name, $description);
    }
}

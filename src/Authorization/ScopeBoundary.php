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

use Vaened\Sentinel\Propagation\ScopePropagationPolicy;
use Vaened\Sentinel\Propagation\TransitiveScopePropagationPolicy;
use Vaened\Sentinel\Scopeable;

final readonly class ScopeBoundary
{
    public function __construct(
        private PermissionEntryProvider $permissions,
        private ScopePropagationPolicy  $propagation = new TransitiveScopePropagationPolicy(),
    )
    {
    }

    public function allows(Scopeable $owner, array $permissions): bool
    {
        if (empty($permissions)) {
            return false;
        }

        foreach ($this->propagation->scopes($owner) as $scope) {
            $entries = $this->permissions->for($scope, ...$permissions);

            foreach ($permissions as $permission) {
                if (!$entries->allows($permission)) {
                    return false;
                }
            }
        }

        return true;
    }
}

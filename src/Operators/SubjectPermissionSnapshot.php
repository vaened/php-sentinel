<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Operators;

use Vaened\Sentinel\Permission;
use Vaened\Sentinel\SubjectPermission;

readonly class SubjectPermissionSnapshot implements SubjectPermission
{
    public function __construct(
        protected Permission $permission,
        protected bool       $isDenied = false,
    )
    {
    }

    public function id(): int|string
    {
        return $this->permission->id();
    }

    public function code(): string
    {
        return $this->permission->code();
    }

    public function name(): string
    {
        return $this->permission->name();
    }

    public function description(): string|null
    {
        return $this->permission->description();
    }

    public function isDenied(): bool
    {
        return $this->isDenied;
    }
}

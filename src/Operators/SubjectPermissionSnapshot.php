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
        private int|string $id,
        private string     $code,
        private bool       $isDenied = false,
    )
    {
    }

    public static function from(Permission $permission, bool $isDenied = false): self
    {
        return new self($permission->id(), $permission->code(), $isDenied);
    }

    public function permissionId(): int|string
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isDenied(): bool
    {
        return $this->isDenied;
    }
}

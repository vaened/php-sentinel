<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Runtime;

use Vaened\Sentinel\Permission;
use Vaened\Sentinel\SubjectPermission;

final class TestSubjectPermission implements SubjectPermission
{
    public function __construct(
        protected int|string $permissionId,
        protected string     $code,
        protected bool       $denied = false,
    )
    {
    }

    public static function from(Permission $permission, bool $denied = false): self
    {
        return new self(
            $permission->id(),
            $permission->code(),
            $denied,
        );
    }

    public function permissionId(): int|string
    {
        return $this->permissionId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isDenied(): bool
    {
        return $this->denied;
    }

    public function deny(): void
    {
        $this->denied = true;
    }

    public function allow(): void
    {
        $this->denied = false;
    }
}

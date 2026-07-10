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

use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\SubjectPermission;
use Vaened\Sentinel\SubjectPermissionState;

final class TestSubjectPermission implements SubjectPermission
{
    public function __construct(
        protected int|string             $permissionId,
        protected string                 $code,
        protected SubjectPermissionState $state = SubjectPermissionState::Direct,
    )
    {
    }

    public static function copy(SubjectPermissionSnapshot $permission): self
    {
        return new self(
            $permission->permissionId(),
            $permission->code(),
            $permission->state(),
        );
    }

    public static function from(Permission $permission, bool $denied = false): self
    {
        return new self(
            $permission->id(),
            $permission->code(),
            SubjectPermissionState::fromBoolean($denied),
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

    public function state(): SubjectPermissionState
    {
        return $this->state;
    }

    public function deny(): void
    {
        $this->state = SubjectPermissionState::Denied;
    }

    public function allow(): void
    {
        $this->state = SubjectPermissionState::Direct;
    }
}

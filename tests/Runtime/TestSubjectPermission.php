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

final class TestSubjectPermission extends TestPermission implements SubjectPermission
{
    public function __construct(
        int|string     $id,
        string         $code,
        string         $name,
        string|null    $description = null,
        protected bool $denied = false,
    )
    {
        parent::__construct($id, $code, $name, $description);
    }

    public static function from(Permission $permission, bool $denied = false): self
    {
        return new self(
            $permission->id(),
            $permission->code(),
            $permission->name(),
            $permission->description(),
            $denied,
        );
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

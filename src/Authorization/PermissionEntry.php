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

readonly class PermissionEntry
{
    public function __construct(
        protected string $code,
        protected bool $denied = false,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function denied(): bool
    {
        return $this->denied;
    }

    public function allowed(): bool
    {
        return !$this->denied;
    }
}

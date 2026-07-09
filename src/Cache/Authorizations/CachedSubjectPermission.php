<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Cache\Authorizations;

use Vaened\Sentinel\SubjectPermission;

final readonly class CachedSubjectPermission implements SubjectPermission
{
    public function __construct(
        private string $code,
        private bool   $denied = false,
    )
    {
    }

    public static function from(string $code, bool $isDenied): self
    {
        return new self($code, $isDenied);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isDenied(): bool
    {
        return $this->denied;
    }
}

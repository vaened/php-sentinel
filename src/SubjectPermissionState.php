<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel;

enum SubjectPermissionState: int
{
    case Denied = 0;

    case Direct = 1;

    case Inherited = 2;

    case DirectInherited = 3;

    case DeniedInherited = 4;

    public static function fromBoolean(bool $denied): self
    {
        return $denied ? self::Denied : self::Direct;
    }

    public function isDirect(): bool
    {
        return self::Direct === $this || self::DirectInherited === $this;
    }

    public function isInherited(): bool
    {
        return self::Inherited === $this || self::DirectInherited === $this || self::DeniedInherited === $this;
    }

    public function isDenied(): bool
    {
        return self::Denied === $this || self::DeniedInherited === $this;
    }

    public function isGranted(): bool
    {
        return !$this->isDenied() && ($this->isDirect() || $this->isInherited());
    }

    public function isOwned(): bool
    {
        return $this->isDenied() || $this->isDirect();
    }

    public function toBoolean(): bool
    {
        return $this->isGranted();
    }

    public function withInherited(): self
    {
        return match ($this) {
            self::Direct => self::DirectInherited,
            self::Denied => self::DeniedInherited,
            default      => $this,
        };
    }
}

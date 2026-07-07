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

use Vaened\Support\Types\SecureList;

class PermissionEntries extends SecureList
{
    public static function type(): string
    {
        return PermissionEntry::class;
    }

    public function find(string $code): PermissionEntry|null
    {
        return $this->pick(static fn(PermissionEntry $entry): bool => $entry->code() === $code);
    }

    public function allows(string $code): bool
    {
        return $this->find($code)?->allowed() ?? false;
    }
}

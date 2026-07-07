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

class RoleEntries extends SecureList
{
    public static function type(): string
    {
        return RoleEntry::class;
    }

    public function find(string $code): RoleEntry|null
    {
        return $this->pick(static fn(RoleEntry $entry): bool => $entry->code() === $code);
    }

    public function has(string $code): bool
    {
        return null !== $this->find($code);
    }
}

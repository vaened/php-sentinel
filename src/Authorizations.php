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

use Vaened\Support\Types\SecureList;

class Authorizations extends SecureList
{
    public static function type(): string
    {
        return Authorization::class;
    }

    public function codes(): array
    {
        return $this->map(static fn(Authorization $authorization): string => $authorization->code())->values();
    }

    public function hasCode(string $code): bool
    {
        return null !== $this->find($code);
    }

    public function find(string $code): Authorization|null
    {
        return $this->pick(static fn(Authorization $authorization): bool => $authorization->code() === $code);
    }

    public function missing(array $codes): array
    {
        return array_values(array_diff($codes, $this->codes()));
    }
}

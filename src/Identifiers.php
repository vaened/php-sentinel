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

final class Identifiers
{
    public static function value(int|string|Identifier $id): int|string
    {
        return $id instanceof Identifier ? $id->value() : $id;
    }
}

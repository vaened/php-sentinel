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

final class Scopes
{
    public static function same(Subject|null $left, Subject|null $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $left::class === $right::class
            && Identifiers::value($left->id()) === Identifiers::value($right->id());
    }
}

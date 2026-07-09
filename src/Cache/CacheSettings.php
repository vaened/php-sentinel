<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Cache;

final readonly class CacheSettings
{
    // default ttl is 12 hours
    public const int DEFAULT_TTL_IN_SECONDS = 43200;

    public function __construct(
        public string   $prefix,
        public int|null $ttl = self::DEFAULT_TTL_IN_SECONDS,
    )
    {
    }
}

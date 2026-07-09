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

use Vaened\Sentinel\Authorization;

readonly class CachedAuthorization implements Authorization
{
    public function __construct(
        protected string $code,
    )
    {
    }

    public static function from(string $code): static
    {
        return new static($code);
    }

    public function code(): string
    {
        return $this->code;
    }
}

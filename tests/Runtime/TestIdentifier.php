<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Runtime;

use Vaened\Sentinel\Identifier;

final readonly class TestIdentifier implements Identifier
{
    public function __construct(
        private string $value,
    )
    {
    }

    public function value(): int|string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

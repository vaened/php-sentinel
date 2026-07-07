<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit;

use Vaened\Sentinel\Identifier;
use Vaened\Sentinel\Identifiers;
use Vaened\Sentinel\Tests\TestCase;

final class IdentifiersTest extends TestCase
{
    public function test_value_returns_scalars_as_is(): void
    {
        self::assertSame(10, Identifiers::value(10));
        self::assertSame('user-1', Identifiers::value('user-1'));
    }

    public function test_value_resolves_identifier_objects_to_native_value(): void
    {
        $identifier = new readonly class('user-1') implements Identifier
        {
            public function __construct(
                private string $value,
            ) {
            }

            public function value(): int|string
            {
                return $this->value;
            }

            public function __toString(): string
            {
                return $this->value;
            }
        };

        self::assertSame('user-1', Identifiers::value($identifier));
    }
}

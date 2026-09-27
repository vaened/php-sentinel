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

use Vaened\Sentinel\Scopes;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class ScopesTest extends TestCase
{
    public function test_same_requires_matching_subject_type_and_identifier(): void
    {
        self::assertTrue(Scopes::same(new TestSubject(1), new TestSubject(1)));
        self::assertFalse(Scopes::same(new TestSubject(1), new TestSubject(2)));
        self::assertFalse(Scopes::same(new TestSubject(1),
            new class implements Subject {
                public function id(): int|string
                {
                    return 1;
                }

                public function scope(): Subject|null
                {
                    return null;
                }
            }));
    }

    public function test_same_handles_unscoped_values(): void
    {
        self::assertTrue(Scopes::same(null, null));
        self::assertFalse(Scopes::same(new TestSubject(1), null));
    }
}

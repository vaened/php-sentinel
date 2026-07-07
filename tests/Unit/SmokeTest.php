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

use Vaened\Sentinel\Tests\TestCase;

final class SmokeTest extends TestCase
{
    public function test_framework_bootstraps(): void
    {
        SmokeTest::assertTrue(true);
    }
}

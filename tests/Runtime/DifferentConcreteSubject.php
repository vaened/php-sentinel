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
use Vaened\Sentinel\Subject;

/**
 * A subject implementation with a different concrete type from TestSubject.
 */
final readonly class DifferentConcreteSubject implements Subject
{
    public function __construct(
        private int|string|Identifier $id,
    )
    {
    }

    public function id(): int|string|Identifier
    {
        return $this->id;
    }

    public function scope(): Subject|null
    {
        return null;
    }
}

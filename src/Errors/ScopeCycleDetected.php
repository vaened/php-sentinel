<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Errors;

use Vaened\Sentinel\Identifiers;
use Vaened\Sentinel\Subject;

class ScopeCycleDetected extends AuthorizationError
{
    public static function forSubject(Subject $subject): static
    {
        return new static(sprintf(
            'Authorization scope cycle detected for subject [%s:%s].',
            $subject::class,
            Identifiers::value($subject->id()),
        ));
    }
}

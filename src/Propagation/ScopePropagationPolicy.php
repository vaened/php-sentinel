<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Propagation;

use Vaened\Sentinel\Scopeable;
use Vaened\Sentinel\Subject;

interface ScopePropagationPolicy
{
    /**
     * @return iterable<Subject>
     */
    public function scopes(Scopeable $owner): iterable;
}

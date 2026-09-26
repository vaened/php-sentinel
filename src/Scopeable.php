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

/**
 * Exposes the intrinsic authorization parent, or null when unscoped.
 * The scope is a subject, not the actor making a request.
 */
interface Scopeable
{
    public function scope(): Subject|null;
}

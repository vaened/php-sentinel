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
 * Resolves a subject without prescribing its source or coupling to a framework.
 */
interface SubjectResolver
{
    public function resolve(): Subject;
}

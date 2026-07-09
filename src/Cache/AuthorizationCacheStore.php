<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Cache;

use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
use Vaened\Sentinel\Subject;

interface AuthorizationCacheStore
{
    public function get(Subject $subject): SubjectAuthorizationProjection|null;

    public function put(Subject $subject, SubjectAuthorizationProjection $projection): void;

    public function forget(Subject $subject): void;

    public function invalidate(): void;

    public function currentVersion(): int;

    public function keyOf(Subject $subject): string;
}

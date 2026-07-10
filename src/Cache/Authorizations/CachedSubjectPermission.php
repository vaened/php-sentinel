<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Cache\Authorizations;

use Vaened\Sentinel\SubjectPermission;
use Vaened\Sentinel\SubjectPermissionState;

final readonly class CachedSubjectPermission implements SubjectPermission
{
    public function __construct(
        private string                 $code,
        private SubjectPermissionState $state,
    )
    {
    }

    public static function from(string $code, SubjectPermissionState $state): self
    {
        return new self($code, $state);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function state(): SubjectPermissionState
    {
        return $this->state;
    }
}

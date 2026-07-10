<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Projection;

use Vaened\Sentinel\Authorization;

final readonly class ProjectionAuthorization implements Authorization
{
    public function __construct(
        private string $code,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }
}

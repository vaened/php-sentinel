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

class InvalidAuthorization extends AuthorizationError
{
    public static function forRoleOwner(): static
    {
        return new static('A role can only receive permissions.');
    }
}

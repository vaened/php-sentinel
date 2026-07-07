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

class PermissionAlreadyExists extends AuthorizationError
{
    public static function fromCode(string $code): static
    {
        return new static(sprintf(
            'Permission [%s] already exists. Permission codes must be unique.',
            $code,
        ));
    }
}

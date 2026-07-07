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

class Permissions extends Authorizations
{
    public static function type(): string
    {
        return Permission::class;
    }

    public function find(string $code): Permission|null
    {
        return parent::find($code);
    }
}

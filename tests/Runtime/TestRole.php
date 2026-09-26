<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Runtime;

use Vaened\Sentinel\Role;
use Vaened\Sentinel\Subject;

final class TestRole extends AbstractAuthorization implements Role
{
    public function __construct(
        int|string             $id,
        string                 $code,
        string                 $name,
        string|null            $description = null,
        protected Subject|null $scope = null,
    )
    {
        parent::__construct($id, $code, $name, $description);
    }

    public function scope(): Subject|null
    {
        return $this->scope;
    }
}

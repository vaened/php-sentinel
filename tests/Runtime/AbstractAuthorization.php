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

use Vaened\Sentinel\Authorization;

abstract class AbstractAuthorization implements Authorization
{
    public function __construct(
        protected int|string $id,
        protected string $code,
        protected string $name,
        protected string|null $description = null,
    ) {
    }

    public function id(): int|string
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string|null
    {
        return $this->description;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function describe(string|null $description): void
    {
        $this->description = $description;
    }
}

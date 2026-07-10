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

use JsonSerializable;

final readonly class SubjectAuthorizationProjection implements JsonSerializable
{
    /**
     * @param list<string> $roles
     * @param array<string, int> $permissions
     */
    public function __construct(
        protected array $roles,
        protected array $permissions,
    ) {
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /**
     * @return array<string, int>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /**
     * @return array{roles: list<string>, permissions: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'roles'       => $this->roles,
            'permissions' => $this->permissions,
        ];
    }

    /**
     * @return array{roles: list<string>, permissions: array<string, int>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

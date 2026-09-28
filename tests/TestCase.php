<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Vaened\Sentinel\Authorization\Authorizer;
use Vaened\Sentinel\Authorization\PermissionEntryProvider;
use Vaened\Sentinel\Authorization\RoleEntryProvider;
use Vaened\Sentinel\Authorization\ScopeBoundary;
use Vaened\Sentinel\Propagation\ScopePropagationPolicy;
use Vaened\Sentinel\Propagation\TransitiveScopePropagationPolicy;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;

abstract class TestCase extends BaseTestCase
{
    protected function createAuthorizer(
        SubjectPermissionRepository $permissions,
        SubjectRoleRepository       $roles,
        ScopePropagationPolicy      $propagation = new TransitiveScopePropagationPolicy(),
    ): Authorizer
    {
        return new Authorizer(
            new PermissionEntryProvider($permissions, $roles),
            new RoleEntryProvider($roles),
            $propagation,
        );
    }

    protected function createScopeBoundary(
        SubjectPermissionRepository $permissions,
        SubjectRoleRepository       $roles,
        ScopePropagationPolicy      $propagation = new TransitiveScopePropagationPolicy(),
    ): ScopeBoundary
    {
        return new ScopeBoundary(
            new PermissionEntryProvider($permissions, $roles),
            $propagation,
        );
    }
}

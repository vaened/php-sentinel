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
use Vaened\Sentinel\Projection\SubjectAuthorizationProjector;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Subject;

final readonly class SubjectAuthorizationProjectionCache
{
    public function __construct(
        private AuthorizationCacheStore     $store,
        private SubjectRoleRepository       $subjectRoles,
        private SubjectPermissionRepository $subjectPermissions,
    )
    {
    }

    public function load(Subject $subject): ?SubjectAuthorizationProjection
    {
        return $this->store->get($subject);
    }

    public function loadOrBuild(Subject $subject): SubjectAuthorizationProjection
    {
        return $this->load($subject) ?? $this->buildAndPersist($subject);
    }

    public function save(Subject $subject, SubjectAuthorizationProjection $projection): void
    {
        $this->store->put($subject, $projection);
    }

    public function forget(Subject $subject): void
    {
        $this->store->forget($subject);
    }

    public function bumpVersion(): void
    {
        $this->store->invalidate();
    }

    public function withRoleAdded(
        SubjectAuthorizationProjection $projection,
        Role                           $role,
        array                          $effectivePermissionCodes,
    ): SubjectAuthorizationProjection
    {
        $roles = $projection->roles();

        if (in_array($role->code(), $roles, true)) {
            return $projection;
        }

        $roles[]     = $role->code();
        $permissions = $projection->permissions();

        foreach ($effectivePermissionCodes as $code) {
            if (!array_key_exists($code, $permissions)) {
                $permissions[$code] = true;
            }
        }

        return new SubjectAuthorizationProjection($roles, $permissions);
    }

    public function build(Subject $subject): SubjectAuthorizationProjection
    {
        $projector = new SubjectAuthorizationProjector(
            $this->subjectRoles,
            $this->subjectPermissions,
        );

        return $projector->project($subject);
    }

    private function buildAndPersist(Subject $subject): SubjectAuthorizationProjection
    {
        $projection = $this->build($subject);
        $this->save($subject, $projection);

        return $projection;
    }
}

<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit\Cache;

use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Cache\Stores\Psr16AuthorizationCacheStore;
use Vaened\Sentinel\Projection\ProjectionAuthorization;
use Vaened\Sentinel\Projection\ProjectionSubjectPermission;
use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\SubjectPermissionState;
use Vaened\Sentinel\Tests\Runtime\InMemoryCache;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class AuthorizationCacheStoreTest extends TestCase
{
    public function test_get_returns_null_when_driver_returns_value_with_mismatched_type(): void
    {
        $raw     = new InMemoryCache();
        $cache   = new Psr16AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $raw->set($this->projectionKey($cache, $subject, 1), 'not-an-array');

        self::assertNull($cache->get($subject));
        self::assertGreaterThan(0, $cache->currentVersion());
    }

    public function test_get_returns_null_when_cached_payload_shape_is_invalid(): void
    {
        $raw     = new InMemoryCache();
        $cache   = new Psr16AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $raw->set($this->projectionKey($cache, $subject, 1), ['roles' => 'nope', 'permissions' => []]);

        self::assertNull($cache->get($subject));
    }

    public function test_version_reinitializes_to_a_fresh_positive_version_when_driver_returns_wrong_type(): void
    {
        $raw = new InMemoryCache();
        $raw->set('sentinel:version', 'not-an-int');

        $cache   = new Psr16AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $cache->put($subject, $this->projection());

        $version = $cache->currentVersion();

        self::assertGreaterThan(0, $version);
        self::assertTrue($raw->has($this->projectionKey($cache, $subject, $version)));
    }

    public function test_version_reinitializes_to_a_fresh_positive_version_when_driver_returns_zero(): void
    {
        $raw = new InMemoryCache();
        $raw->set('sentinel:version', 0);

        $cache   = new Psr16AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $cache->put($subject, $this->projection());

        $version = $cache->currentVersion();

        self::assertGreaterThan(0, $version);
        self::assertTrue($raw->has($this->projectionKey($cache, $subject, $version)));
    }

    public function test_version_increments_when_invalidate_is_called(): void
    {
        $cache = new Psr16AuthorizationCacheStore(new InMemoryCache(), new CacheSettings(prefix: 'sentinel'));

        $initialVersion = $cache->currentVersion();

        self::assertGreaterThan(0, $initialVersion);

        $cache->invalidate();

        self::assertSame($initialVersion + 1, $cache->currentVersion());
    }

    public function test_missing_version_does_not_reuse_an_old_projection_namespace(): void
    {
        $raw     = new InMemoryCache();
        $cache   = new Psr16AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $cache->put($subject,
            $this->projection(
                permissions: [new ProjectionSubjectPermission('posts.edit', SubjectPermissionState::Direct)],
            ));
        $cache->invalidate();
        $cache->put($subject,
            $this->projection(
                permissions: [new ProjectionSubjectPermission('posts.edit', SubjectPermissionState::Denied)],
            ));

        $raw->delete('sentinel:version');

        self::assertNull($cache->get($subject));
    }

    public function test_put_and_get_round_trip_preserves_the_projection(): void
    {
        $cache      = new Psr16AuthorizationCacheStore(new InMemoryCache(), new CacheSettings(prefix: 'sentinel'));
        $subject    = new TestSubject(1);
        $projection = $this->projection(
            [new ProjectionAuthorization('admin')],
            [new ProjectionSubjectPermission('users.read', SubjectPermissionState::Direct)],
        );

        $cache->put($subject, $projection);

        self::assertSame($projection->toArray(), $cache->get($subject)?->toArray());
    }

    public function test_forget_removes_the_subject_projection(): void
    {
        $cache   = new Psr16AuthorizationCacheStore(new InMemoryCache(), new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $cache->put($subject, $this->projection());
        $cache->forget($subject);

        self::assertNull($cache->get($subject));
    }

    private function projectionKey(Psr16AuthorizationCacheStore $cache, TestSubject $subject, int $version): string
    {
        return sprintf('sentinel:v%s:%s', $version, $cache->keyOf($subject));
    }

    private function projection(array $roles = [], array $permissions = []): SubjectAuthorizationProjection
    {
        return new SubjectAuthorizationProjection(
            new Authorizations($roles),
            new SubjectPermissions($permissions),
        );
    }
}

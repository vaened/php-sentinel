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

use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Cache\Stores\Psr16AuthorizationCacheStore;
use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
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

        $raw->set('sentinel:v1:' . $cache->keyOf($subject), 'not-an-array');

        self::assertNull($cache->get($subject));
        self::assertSame(1, $cache->currentVersion());
    }

    public function test_get_returns_null_when_cached_payload_shape_is_invalid(): void
    {
        $raw     = new InMemoryCache();
        $cache   = new Psr16AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $raw->set('sentinel:v1:' . $cache->keyOf($subject), ['roles' => 'nope', 'permissions' => []]);

        self::assertNull($cache->get($subject));
    }

    public function test_version_defaults_to_one_when_driver_returns_wrong_type(): void
    {
        $raw = new InMemoryCache();
        $raw->set('sentinel:version', 'not-an-int');

        $cache   = new Psr16AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $cache->put($subject, new SubjectAuthorizationProjection([], []));

        self::assertArrayHasKey('sentinel:v1:' . $cache->keyOf($subject), $this->inspectItems($raw));
    }

    public function test_version_defaults_to_one_when_driver_returns_zero(): void
    {
        $raw = new InMemoryCache();
        $raw->set('sentinel:version', 0);

        $cache   = new Psr16AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $cache->put($subject, new SubjectAuthorizationProjection([], []));

        self::assertArrayHasKey('sentinel:v1:' . $cache->keyOf($subject), $this->inspectItems($raw));
    }

    public function test_version_increments_when_invalidate_is_called(): void
    {
        $cache = new Psr16AuthorizationCacheStore(new InMemoryCache(), new CacheSettings(prefix: 'sentinel'));

        self::assertSame(1, $cache->currentVersion());

        $cache->invalidate();

        self::assertSame(2, $cache->currentVersion());
    }

    public function test_put_and_get_round_trip_preserves_the_projection(): void
    {
        $cache      = new Psr16AuthorizationCacheStore(new InMemoryCache(), new CacheSettings(prefix: 'sentinel'));
        $subject    = new TestSubject(1);
        $projection = new SubjectAuthorizationProjection(
            ['admin'],
            ['users.read' => true],
        );

        $cache->put($subject, $projection);

        self::assertSame($projection->toArray(), $cache->get($subject)?->toArray());
    }

    public function test_forget_removes_the_subject_projection(): void
    {
        $cache   = new Psr16AuthorizationCacheStore(new InMemoryCache(), new CacheSettings(prefix: 'sentinel'));
        $subject = new TestSubject(1);

        $cache->put($subject, new SubjectAuthorizationProjection([], []));
        $cache->forget($subject);

        self::assertNull($cache->get($subject));
    }

    private function inspectItems(InMemoryCache $cache): array
    {
        $reflection = new \ReflectionProperty(InMemoryCache::class, 'items');

        return $reflection->getValue($cache);
    }
}

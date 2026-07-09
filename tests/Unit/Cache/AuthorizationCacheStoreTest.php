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

use Vaened\Sentinel\Cache\AuthorizationCacheStore;
use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Tests\Runtime\InMemoryCache;

final class AuthorizationCacheStoreTest extends CacheTestCase
{
    public function test_get_returns_default_when_driver_returns_value_with_mismatched_type(): void
    {
        $raw   = new InMemoryCache();
        $cache = new AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));

        $raw->set('sentinel:v1:user-key', 'not-an-array');

        self::assertSame([], $cache->get('user-key', []));
        self::assertSame(1, $cache->get('version-key', 1));
        self::assertNull($cache->get('absent-key'));
    }

    public function test_get_returns_stored_value_when_type_matches_default(): void
    {
        $raw   = new InMemoryCache();
        $cache = new AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));

        $payload = ['roles' => ['admin'], 'permissions' => ['users.read' => true]];
        $raw->set('sentinel:v1:user-key', $payload);

        self::assertSame($payload, $cache->get('user-key', []));
    }

    public function test_version_defaults_to_one_when_driver_returns_wrong_type(): void
    {
        $raw = new InMemoryCache();
        $raw->set('sentinel:version', 'not-an-int');

        $cache = new AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));

        $cache->put('user-key', ['roles' => [], 'permissions' => []]);

        self::assertArrayHasKey('sentinel:v1:user-key', $this->inspectItems($raw));
    }

    public function test_version_defaults_to_one_when_driver_returns_zero(): void
    {
        $raw = new InMemoryCache();
        $raw->set('sentinel:version', 0);

        $cache = new AuthorizationCacheStore($raw, new CacheSettings(prefix: 'sentinel'));

        $cache->put('user-key', ['roles' => [], 'permissions' => []]);

        self::assertArrayHasKey('sentinel:v1:user-key', $this->inspectItems($raw));
    }

    public function test_version_increments_when_invalidate_is_called(): void
    {
        $cache = new AuthorizationCacheStore($this->store, $this->extractSettings());

        self::assertSame(1, $this->cacheVersion($cache));

        $cache->invalidate();

        self::assertSame(2, $this->cacheVersion($cache));
    }

    public function test_put_and_get_round_trip_preserves_arrays(): void
    {
        $cache   = new AuthorizationCacheStore($this->store, $this->extractSettings());
        $payload = ['roles' => ['admin'], 'permissions' => ['users.read' => true]];

        $cache->put('user-key', $payload);

        self::assertSame($payload, $cache->get('user-key', []));
    }

    public function test_forget_removes_the_namespaced_key(): void
    {
        $cache = new AuthorizationCacheStore($this->store, $this->extractSettings());
        $cache->put('user-key', ['roles' => [], 'permissions' => []]);

        $cache->forget('user-key');

        self::assertSame([], $cache->get('user-key', []));
    }

    private function extractSettings(): CacheSettings
    {
        $reflection = new \ReflectionProperty(AuthorizationCacheStore::class, 'settings');
        return $reflection->getValue($this->cache);
    }

    private function inspectItems(InMemoryCache $cache): array
    {
        $reflection = new \ReflectionProperty(InMemoryCache::class, 'items');
        return $reflection->getValue($cache);
    }
}
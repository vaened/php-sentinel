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

use Psr\SimpleCache\CacheInterface;

final readonly class AuthorizationCacheStore
{
    public function __construct(
        private CacheInterface $cache,
        private CacheSettings  $settings,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->cache->get($this->namespaced($key), $default);

        if ($default === null) {
            return $value;
        }

        return gettype($value) === gettype($default) ? $value : $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->cache->set($this->namespaced($key), $value, $this->settings->ttl);
    }

    public function forget(string $key): void
    {
        $this->cache->delete($this->namespaced($key));
    }

    public function invalidate(): void
    {
        $this->cache->set($this->versionKey(), $this->version() + 1);
    }

    public function currentVersion(): int
    {
        return $this->version();
    }

    private function namespaced(string $key): string
    {
        return sprintf('%s:%s', $this->namespace(), $key);
    }

    private function namespace(): string
    {
        return sprintf('%s:v%s', $this->settings->prefix, $this->version());
    }

    private function version(): int
    {
        $value = $this->cache->get($this->versionKey(), 1);
        return is_int($value) && $value > 0 ? $value : 1;
    }

    private function versionKey(): string
    {
        return sprintf('%s:version', $this->settings->prefix);
    }
}

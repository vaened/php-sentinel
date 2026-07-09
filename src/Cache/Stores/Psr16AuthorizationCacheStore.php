<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Cache\Stores;

use Psr\SimpleCache\CacheInterface;
use Vaened\Sentinel\Cache\AuthorizationCacheStore;
use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Identifiers;
use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
use Vaened\Sentinel\Subject;

final readonly class Psr16AuthorizationCacheStore implements AuthorizationCacheStore
{
    public function __construct(
        private CacheInterface $cache,
        private CacheSettings  $settings,
    ) {
    }

    public function get(Subject $subject): SubjectAuthorizationProjection|null
    {
        $value = $this->cache->get($this->namespaced($this->keyOf($subject)), null);

        if (!is_array($value)) {
            return null;
        }

        $roles = $value['roles'] ?? null;
        $permissions = $value['permissions'] ?? null;

        if (!is_array($roles) || !is_array($permissions)) {
            return null;
        }

        return new SubjectAuthorizationProjection($roles, $permissions);
    }

    public function put(Subject $subject, SubjectAuthorizationProjection $projection): void
    {
        $this->cache->set($this->namespaced($this->keyOf($subject)), $projection->toArray(), $this->settings->ttl);
    }

    public function forget(Subject $subject): void
    {
        $this->cache->delete($this->namespaced($this->keyOf($subject)));
    }

    public function invalidate(): void
    {
        $this->cache->set($this->versionKey(), $this->version() + 1);
    }

    public function currentVersion(): int
    {
        return $this->version();
    }

    public function keyOf(Subject $subject): string
    {
        return sprintf(
            'subject:%s:%s:projection',
            $subject::class,
            Identifiers::value($subject->id()),
        );
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

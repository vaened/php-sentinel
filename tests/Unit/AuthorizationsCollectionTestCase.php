<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit;

use Vaened\Sentinel\Authorization;
use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Tests\TestCase;

abstract class AuthorizationsCollectionTestCase extends TestCase
{
    abstract protected function create(int $id, string $code): Authorization;

    abstract protected function collect(array $entities): Authorizations;

    abstract protected function expectedType(): string;

    public function test_find_returns_the_entity_with_matching_code(): void
    {
        $admin  = $this->create(1, 'admin');
        $editor = $this->create(2, 'editor');
        $collection = $this->collect([$admin, $editor]);

        self::assertSame($admin, $collection->find('admin'));
    }

    public function test_find_returns_null_when_code_is_unknown(): void
    {
        $collection = $this->collect([$this->create(1, 'admin')]);

        self::assertNull($collection->find('unknown'));
    }

    public function test_has_code_returns_true_only_for_present_codes(): void
    {
        $collection = $this->collect([$this->create(1, 'admin')]);

        self::assertTrue($collection->hasCode('admin'));
        self::assertFalse($collection->hasCode('unknown'));
    }

    public function test_codes_returns_all_codes_in_insertion_order(): void
    {
        $collection = $this->collect([
            $this->create(1, 'admin'),
            $this->create(2, 'editor'),
        ]);

        self::assertSame(['admin', 'editor'], $collection->codes());
    }

    public function test_missing_lists_codes_not_present_in_the_collection(): void
    {
        $collection = $this->collect([$this->create(1, 'admin')]);

        self::assertSame(['editor', 'viewer'], $collection->missing(['admin', 'editor', 'viewer']));
    }

    public function test_missing_returns_empty_when_every_code_is_present(): void
    {
        $collection = $this->collect([
            $this->create(1, 'admin'),
            $this->create(2, 'editor'),
        ]);

        self::assertSame([], $collection->missing(['admin', 'editor']));
    }

    public function test_collection_type_discriminates_the_contained_entity(): void
    {
        $collection = $this->collect([]);

        self::assertSame($this->expectedType(), $collection::type());
    }
}

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

use Vaened\Sentinel\Authorization\RoleEntryProvider;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class RoleEntryProviderTest extends TestCase
{
    private SubjectRoleRepository $subjectRoles;
    private RoleEntryProvider     $provider;
    private TestSubject           $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $this->provider     = new RoleEntryProvider($this->subjectRoles);

        $this->subject = new TestSubject(1);
    }

    public function test_returns_empty_entries_when_no_codes_are_requested(): void
    {
        $entries = $this->provider->for($this->subject);

        self::assertCount(0, $entries);
    }

    public function test_creates_entries_for_each_role_returned_by_the_repository(): void
    {
        $admin  = new TestRole(10, 'admin', 'Admin');
        $editor = new TestRole(11, 'editor', 'Editor');

        $this->subjectRoles->method('lookup')
            ->willReturn(new Roles([$admin, $editor]));

        $entries = $this->provider->for($this->subject, 'admin', 'editor');

        self::assertCount(2, $entries);
        self::assertTrue($entries->has('admin'));
        self::assertTrue($entries->has('editor'));
    }

    public function test_returns_empty_entries_when_repository_returns_no_roles(): void
    {
        $this->subjectRoles->method('lookup')
            ->willReturn(new Roles([]));

        $entries = $this->provider->for($this->subject, 'admin');

        self::assertCount(0, $entries);
    }
}

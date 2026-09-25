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

use PHPUnit\Framework\Attributes\TestWith;
use Vaened\Sentinel\SubjectPermissionState;
use Vaened\Sentinel\Tests\TestCase;

final class SubjectPermissionStateTest extends TestCase
{
    #[TestWith([SubjectPermissionState::Direct, true, false, false, true, true])]
    #[TestWith([SubjectPermissionState::Inherited, false, true, false, true, false])]
    #[TestWith([SubjectPermissionState::DirectInherited, true, true, false, true, true])]
    #[TestWith([SubjectPermissionState::Denied, false, false, true, false, true])]
    #[TestWith([SubjectPermissionState::DeniedInherited, false, true, true, false, true])]
    public function test_predicates_describe_own_and_inherited_permission_facts(
        SubjectPermissionState $state,
        bool                   $direct,
        bool                   $inherited,
        bool                   $denied,
        bool                   $granted,
        bool                   $owned,
    ): void
    {
        self::assertSame($direct, $state->isDirect());
        self::assertSame($inherited, $state->isInherited());
        self::assertSame($denied, $state->isDenied());
        self::assertSame($granted, $state->isGranted());
        self::assertSame($owned, $state->isOwned());
    }
}

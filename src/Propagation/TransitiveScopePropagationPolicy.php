<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Propagation;

use Vaened\Sentinel\Errors\ScopeCycleDetected;
use Vaened\Sentinel\Identifiers;
use Vaened\Sentinel\Scopeable;
use Vaened\Sentinel\Subject;

final class TransitiveScopePropagationPolicy implements ScopePropagationPolicy
{
    public function scopes(Scopeable $owner): iterable
    {
        $seen = [];

        if ($owner instanceof Subject) {
            $seen[$this->identity($owner)] = true;
        }

        for ($scope = $owner->scope(); $scope !== null; $scope = $scope->scope()) {
            $identity = $this->identity($scope);

            if (isset($seen[$identity])) {
                throw ScopeCycleDetected::forSubject($scope);
            }

            $seen[$identity] = true;

            yield $scope;
        }
    }

    private function identity(Subject $subject): string
    {
        $id = Identifiers::value($subject->id());

        return sprintf('%s:%s:%s', $subject::class, get_debug_type($id), $id);
    }
}

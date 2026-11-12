<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests;

use c975L\ConfigBundle\Test\CodeRulesTestCase;

// The audit's rules replayed on the bundle's code and templates (see ConfigBundle's CodeRulesTestCase)
class CodeRulesTest extends CodeRulesTestCase
{
    // The code the rules read
    protected function sourceDirectory(): string
    {
        return __DIR__ . '/../src';
    }

    // Its namespace, to read its classes by reflection
    protected function sourceNamespace(): string
    {
        return 'c975L\BookBundle';
    }

    // The legitimate exceptions, each with its reason
    protected function allowed(): array
    {
        return [
            self::BULK_QUERY => [
                'c975L\BookBundle\Repository\BookReleaseAlertRepository::deleteCreatedBefore' => 'Release alerts are read by the sending command alone, never cached',
            ],
        ];
    }
}

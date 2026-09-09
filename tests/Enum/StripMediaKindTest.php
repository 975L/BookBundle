<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Enum;

use c975L\BookBundle\Enum\StripMediaKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\XliffFileLoader;

class StripMediaKindTest extends TestCase
{
    /** @return iterable<string, array{StripMediaKind}> */
    public static function kinds(): iterable
    {
        foreach (StripMediaKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    // A role added to the enum without its label would reach the back-office as its own translation key
    #[DataProvider('kinds')]
    public function testEveryRoleIsNamedInEveryLocale(StripMediaKind $kind): void
    {
        $loader = new XliffFileLoader();

        foreach (['fr', 'en', 'es'] as $locale) {
            $catalogue = $loader->load(dirname(__DIR__, 2) . '/translations/book.' . $locale . '.xlf', $locale, 'book');

            $this->assertTrue(
                $catalogue->has($kind->label(), 'book'),
                sprintf('"%s" names no "%s" in the "%s" catalogue.', $kind->value, $kind->label(), $locale)
            );
        }
    }

    // The value is what a media row stores and what a template asks for: renaming a case would leave every planche already imported pointing at a role nothing reads
    public function testTheStoredValuesAreTheOnesTheDataCarries(): void
    {
        $this->assertSame(
            ['case', 'page', 'thumbnail', 'card'],
            array_column(StripMediaKind::cases(), 'value')
        );
    }

    // The form offers every role, each under the key naming it, so a back-office cannot write a value the page has no branch for
    public function testTheFormOffersEveryRole(): void
    {
        $choices = StripMediaKind::choices();

        $this->assertCount(count(StripMediaKind::cases()), $choices);
        $this->assertSame('case', $choices['label.strip_media_case']);
        $this->assertSame('card', $choices['label.strip_media_card']);
    }
}

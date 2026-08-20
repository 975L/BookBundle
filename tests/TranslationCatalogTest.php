<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// A label renamed in a form and forgotten in the catalogs shows as its raw key, which nothing else here would catch
class TranslationCatalogTest extends TestCase
{
    private const array LOCALES = ['fr', 'en', 'es'];

    /** @return array<string, string> */
    private static function catalog(string $locale): array
    {
        $xml = simplexml_load_file(dirname(__DIR__) . '/translations/book.' . $locale . '.xlf');
        $keys = [];
        foreach ($xml->file->body->{'trans-unit'} as $unit) {
            $keys[(string) $unit->source] = (string) $unit->target;
        }

        return $keys;
    }

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        foreach (self::LOCALES as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('locales')]
    public function testTheKeysTheFormsNameAreTranslated(string $locale): void
    {
        $catalog = self::catalog($locale);

        foreach (['label.links', 'label.link_kind', 'label.link_url', 'label.link_url-help', 'label.edition', 'label.edition_kind', 'label.edition_medias-help', 'label.edition_links-help', 'label.editions_none', 'label.editions_save_first', 'action.edition_new', 'label.info_book_edition'] as $key) {
            $this->assertArrayHasKey($key, $catalog, sprintf('"%s" is missing from the %s catalog', $key, $locale));
            $this->assertNotSame('', $catalog[$key]);
        }
    }

    public function testTheThreeCatalogsHoldTheSameKeys(): void
    {
        $reference = array_keys(self::catalog('fr'));
        sort($reference);

        foreach (['en', 'es'] as $locale) {
            $keys = array_keys(self::catalog($locale));
            sort($keys);
            $this->assertSame($reference, $keys, sprintf('The %s catalog diverges from the french one', $locale));
        }
    }
}

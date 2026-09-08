<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Email;

use c975L\BookBundle\Email\BookEmailTemplateProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * What this bundle seeds into a site's EmailTemplate rows.
 *
 * Built against the real catalogues rather than a stub translator: a mistyped key is not an error anywhere, trans()
 * hands back the key itself, and it would be that string - not the sentence - that gets seeded and then mailed.
 */
class BookEmailTemplateProviderTest extends TestCase
{
    // The two halves of one promise, written in the three languages this bundle ships
    public function testBothEmailsAreDeclaredInEveryLanguageTheBundleShips(): void
    {
        $templates = $this->provider()->getEmailTemplates();

        $this->assertSame(
            [BookEmailTemplateProvider::RELEASE_ALERT_CONFIRMATION, BookEmailTemplateProvider::BOOK_RELEASED],
            array_keys($templates)
        );

        foreach ($templates as $blocksByLocale) {
            $this->assertSame(['fr', 'en', 'es'], array_keys($blocksByLocale));
        }
    }

    public function testNothingComposedIntoTheEmailsIsAnUntranslatedKey(): void
    {
        foreach ($this->provider()->getEmailTemplates() as $name => $blocksByLocale) {
            foreach ($blocksByLocale as $locale => $blocks) {
                foreach ($blocks as [, $heading, , $content, $label]) {
                    foreach ([$heading, $content, $label] as $wording) {
                        $this->assertDoesNotMatchRegularExpression(
                            '/^(label|text)\./',
                            (string) $wording,
                            sprintf('"%s" (%s, %s) holds an untranslated key, which would be mailed as-is', $wording, $name, $locale)
                        );
                    }
                }
            }
        }
    }

    /**
     * The placeholders the sentences carry are exactly the ones the service fills in.
     *
     * A "{{ }}" nobody fills is mailed as itself, and a value nobody placed is dropped in silence: both are only
     * ever noticed by the reader who receives the message.
     */
    public function testEveryPlaceholderIsOneTheServiceFillsIn(): void
    {
        $filled = ['book_title', 'book_url', 'unsubscribe_url'];
        $placed = [];

        foreach ($this->provider()->getEmailTemplates() as $blocksByLocale) {
            foreach ($blocksByLocale as $blocks) {
                foreach ($blocks as [, $heading, , $content, $label, $url]) {
                    preg_match_all('/\{\{\s*([a-z_]+)\s*}}/', $heading . $content . $label . $url, $matches);
                    $placed = [...$placed, ...$matches[1]];
                }
            }
        }

        $placed = array_values(array_unique($placed));
        sort($placed);
        sort($filled);

        $this->assertSame($filled, $placed);
    }

    // Written down as urls and nothing else, the reader was left to copy them into a browser by hand
    public function testTheAcknowledgementLinksTheBookAndTheWayOut(): void
    {
        foreach ($this->provider()->getEmailTemplates()[BookEmailTemplateProvider::RELEASE_ALERT_CONFIRMATION] as $blocks) {
            $this->assertSame('html', $blocks[0][0]);
            $this->assertStringContainsString('<a href="{{ book_url }}">{{ book_title }}</a>', (string) $blocks[0][3]);

            $this->assertSame('html', $blocks[2][0]);
            $this->assertStringContainsString('<a href="{{ unsubscribe_url }}">{{ unsubscribe_url }}</a>', (string) $blocks[2][3]);
        }
    }

    // The parution's link is a button rather than an anchor, that e-mail having one thing to say and one place to send the reader
    public function testTheParutionKeepsItsButton(): void
    {
        foreach ($this->provider()->getEmailTemplates()[BookEmailTemplateProvider::BOOK_RELEASED] as $blocks) {
            $this->assertSame('button', $blocks[1][0]);
            $this->assertSame('{{ book_url }}', $blocks[1][5]);
        }
    }

    // Read from translations/, so a catalogue and a declaration cannot drift
    private function provider(): BookEmailTemplateProvider
    {
        $translator = new Translator('fr');
        $translator->addLoader('xlf', new XliffFileLoader());
        foreach (['fr', 'en', 'es'] as $locale) {
            $translator->addResource('xlf', __DIR__ . '/../../translations/book.' . $locale . '.xlf', $locale, 'book');
        }

        return new BookEmailTemplateProvider($translator);
    }
}

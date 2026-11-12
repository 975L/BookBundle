<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

// What a planche's page says of itself in its description metas: its summary when it is long enough to describe the page, a sentence naming it and the site otherwise. The lines setting it are taken out of the page and rendered on their own, the rest of the page asking for a whole kernel
class StripDescriptionTest extends TestCase
{
    private const string DISPLAY = __DIR__ . '/../../templates/strip/display.html.twig';

    // A reply of a few words is said inside the sentence, its paragraphs parted by a space
    public function testAShortSummaryIsSaidInsideASentence(): void
    {
        $this->assertSame(
            'Réplique 007, une planche de Papa Câlin à lire en ligne. — On mange quoi ? — Ce qu’il y aura 😉',
            $this->describe('<p>— On mange quoi ?</p><p>— Ce qu’il y aura 😉</p>'),
        );
    }

    // A summary long enough to describe the page is kept as written
    public function testALongSummaryIsKeptAsWritten(): void
    {
        $summary = '<p>Papa Câlin raconte à ses enfants une histoire de dragons, de princesses et de chevaliers.</p>';

        $this->assertSame($summary, $this->describe($summary));
    }

    // A planche drawn without any words still describes itself
    public function testAPlancheWithoutSummaryIsStillDescribed(): void
    {
        $this->assertSame('Réplique 007, une planche de Papa Câlin à lire en ligne.', $this->describe(null));
    }

    // A planche still to come hands its layout neither its reply nor its drawing, and asks to stay out of search engines
    public function testAPlancheStillToComeGivesNothingAway(): void
    {
        $this->assertSame('||noindex, follow', $this->describe('<p>— On mange quoi ?</p>', false, '{{ summarySocialNetwork }}|{{ ogImage }}|{{ robots }}'));
    }

    // A published planche shares its drawing and stays indexable
    public function testAPublishedPlancheSharesItsDrawing(): void
    {
        $this->assertSame('/medias/strip-1.webp|', $this->describe(null, true, '{{ ogImage }}|{{ robots }}'));
    }

    // The metas the page hands its layout for a planche carrying this summary, in French
    private function describe(?string $summary, bool $isPublished = true, string $output = '{{ summarySocialNetwork }}'): string
    {
        $this->assertSame(1, preg_match('/\{% set summaryText = .+?\n\{% set robots = .+?%\}/s', (string) file_get_contents(self::DISPLAY), $matches), 'The page no longer sets its description this way - check this test still says what it means.');

        $translator = new Translator('fr');
        $translator->addLoader('xlf', new XliffFileLoader());
        $translator->addResource('xlf', __DIR__ . '/../../translations/book.fr.xlf', 'fr', 'book');

        $twig = new Environment(new ArrayLoader(['strip' => '{% set isPublished = published %}' . $matches[0] . $output]), ['autoescape' => false]);
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addFunction(new TwigFunction('config', static fn (string $name): string => 'Papa Câlin'));
        $twig->addFunction(new TwigFunction('absolute_url', static fn (string $path): string => $path));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => $path));

        return $twig->render('strip', ['published' => $isPublished, 'strip' => ['title' => 'Réplique 007', 'summary' => $summary, 'shareMedia' => '/medias/strip-1.webp']]);
    }
}

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

// What a contributor's page says of itself in its description metas: the biography written for them, a sentence naming them and the site without one. The line setting it is taken out of the page and rendered on its own, the rest of the page asking for a whole kernel
class ContributorDescriptionTest extends TestCase
{
    private const string DISPLAY = __DIR__ . '/../../templates/contributor/display.html.twig';

    // A biography is kept as written
    public function testABiographyIsKeptAsWritten(): void
    {
        $this->assertSame('<p>Camille Ferrand écrit des romans.</p>', $this->describe('<p>Camille Ferrand écrit des romans.</p>'));
    }

    // A contributor filed without one, or with an empty paragraph, is still described
    public function testAContributorWithoutBiographyIsStillDescribed(): void
    {
        foreach ([null, '<p></p>'] as $summary) {
            $this->assertSame('Camille Ferrand, publié par Bundles 975L : sa présentation et la liste de ses livres.', $this->describe($summary));
        }
    }

    // The description the page hands its layout for a contributor carrying this biography, in French
    private function describe(?string $summary): string
    {
        $this->assertSame(1, preg_match('/\{% set summarySocialNetwork = .+?%\}/', (string) file_get_contents(self::DISPLAY), $matches), 'The page no longer sets its description this way - check this test still says what it means.');

        $translator = new Translator('fr');
        $translator->addLoader('xlf', new XliffFileLoader());
        $translator->addResource('xlf', __DIR__ . '/../../translations/book.fr.xlf', 'fr', 'book');

        $twig = new Environment(new ArrayLoader(['contributor' => $matches[0] . '{{ summarySocialNetwork }}']), ['autoescape' => false]);
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addFunction(new TwigFunction('config', static fn (string $name): string => 'Bundles 975L'));

        return $twig->render('contributor', ['contributor' => ['name' => 'Camille Ferrand', 'summary' => $summary]]);
    }
}

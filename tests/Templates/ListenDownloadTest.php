<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Templates;

use c975L\BookBundle\Entity\Book;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// The listening page hands UiBundle's download button every file its player needs again offline. Nothing renders here, so the call is read where it is written
class ListenDownloadTest extends TestCase
{
    private const string DISPLAY = __DIR__ . '/../../templates/listen/display.html.twig';

    public function testThePageMountsTheDownloadButton(): void
    {
        $this->assertStringContainsString('<twig:c975LUi:Pwa:Download name="book-listen-{{ book.id }}"', $this->contents());
    }

    // The recording, the pages and their timecodes: a story missing one of them plays wrong in airplane mode
    public function testTheRecordingThePagesAndTheirTimecodesAreKept(): void
    {
        $contents = $this->contents();

        $this->assertStringContainsString('{% set offlineUrls = [vich_uploader_asset(audio), coverUrl] %}', $contents);
        $this->assertStringContainsString('book.pages|map(page => vich_uploader_asset(page))', $contents);
        $this->assertStringContainsString('vich_uploader_asset(book.cues)', $contents);
    }

    // A timecodes row holding no file follows nothing: the reader and the kept files read the one same test
    public function testATimecodesRowHoldingNoFileIsNotFollowed(): void
    {
        $contents = $this->contents();

        $this->assertStringContainsString('{% set followed = book.cues and book.cues.name and book.pages|length %}', $contents);
        $this->assertSame(2, substr_count($contents, '{% if followed %}'));
    }

    // The book's own page offers the listening page only when it answers (see ListenController::display()), on a book carrying its timecodes and its pages
    public function testTheBooksPageLinksToTheListeningPageOnlyWhenItAnswers(): void
    {
        $this->assertStringNotContainsString('url="/listen"', $this->podcasts(new \DateTime('+1 year'), withLink: true));
        $this->assertStringNotContainsString('url="/listen"', $this->podcasts(null, withLink: true));
        $this->assertStringContainsString('url="/listen"', $this->podcasts(new \DateTime('2020-01-01'), withLink: true));
    }

    // The page of a book still to come: the app links and none of the recording, the file's address included
    public function testABookToComeShowsItsPodcastAppsAndNotItsRecording(): void
    {
        $page = $this->podcasts(new \DateTime('+1 year'), withLink: true);

        $this->assertStringContainsString('https://example.com/spotify', $page);
        $this->assertStringNotContainsString('c975LUi:Audio:Audio', $page);
        $this->assertStringNotContainsString('label.download', $page);
        $this->assertStringNotContainsString('story.mp3', $page);
    }

    // No date at all reads the same, and with no link left there is no card
    public function testABookToComeWithNoLinkShowsNoCard(): void
    {
        $this->assertSame('', trim($this->podcasts(null, withLink: false)));
    }

    public function testABookOutShowsItsRecordingAndItsDownloads(): void
    {
        $page = $this->podcasts(new \DateTime('2020-01-01'), withLink: true);

        $this->assertStringContainsString('c975LUi:Audio:Audio', $page);
        $this->assertStringContainsString('label.download', $page);
        $this->assertStringContainsString('/medias/story.mp3', $page);
        $this->assertStringContainsString('https://example.com/spotify', $page);
    }

    // The component's own file, only its {% props %} tag and the card around it left out: nothing here parses a <twig:> tag, which comes out as text and can be read
    private function podcasts(?\DateTime $published, bool $withLink): string
    {
        $source = (string) preg_replace('/\{% props .*? %\}/', '', (string) file_get_contents(__DIR__ . '/../../templates/components/Book/Podcasts.html.twig'));

        $twig = new Environment(new ArrayLoader(['podcasts' => $source, '@c975LBook/book/_section.html.twig' => '{{ body }}']));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFunction(new TwigFunction('book_ui_locale', static fn (): string => 'fr'));
        $twig->addFunction(new TwigFunction('book_audio_medias', static fn (): array => [['mimeType' => 'audio/mpeg', 'name' => 'story.mp3']]));
        $twig->addFunction(new TwigFunction('book_links_of', static fn (): array => $withLink ? ['spotify'] : []));
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (array $media): string => '/medias/' . $media['name']));
        $twig->addFunction(new TwigFunction('book_path', static fn (): string => '/listen'));
        $twig->addFunction(new TwigFunction('book_link_label', static fn (): string => 'Spotify'));
        $twig->addFunction(new TwigFunction('book_link_icon', static fn (): string => '/spotify.svg'));
        $twig->addFunction(new TwigFunction('book_link_url', static fn (): string => 'https://example.com/spotify'));

        $book = new Book()->setSlug('a')->setPublished($published);

        return $twig->render('podcasts', [
            'book' => ['slug' => 'a', 'title' => 'A', 'language' => 'fr', 'cues' => true, 'pages' => [1], 'isToBePublished' => $book->isToBePublished()],
            'section' => [],
            'editUrl' => '',
        ]);
    }

    private function contents(): string
    {
        $this->assertFileExists(self::DISPLAY);

        return (string) file_get_contents(self::DISPLAY);
    }
}

<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The inside of a book waits for its day, as its recording and its documents to print do: a book announced shows its cover, its summary and its details only
class ToBePublishedTest extends TestCase
{
    // The components showing the inside of the book and the line that holds each back
    public static function components(): iterable
    {
        yield 'pages' => ['Pages', '{% set medias = book.isToBePublished ? [] : book.pages %}'];
        yield 'flipbook' => ['Flipbook', '{% if not book.isToBePublished and book.flipbooks|length %}'];
        yield 'videos' => ['Videos', '{% if not book.isToBePublished and (book.videos is not empty or links|length) %}'];
    }

    // Each component holds its line, as the recording's and the printables' do
    #[DataProvider('components')]
    public function testTheInsideWaitsForTheBookToBeOut(string $component, string $line): void
    {
        $this->assertStringContainsString($line, (string) file_get_contents(__DIR__ . '/../../templates/components/Book/' . $component . '.html.twig'));
    }
}

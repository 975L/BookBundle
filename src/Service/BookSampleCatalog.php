<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

/**
 * The made-up catalog this bundle stands behind, held as plain data and read by its two consumers.
 *
 * GalleryShowcaseProvider turns it into never-persisted entities a block showcase renders, BookDemoFixtureProvider
 * into rows a demo site is browsed for. One dataset, two readings: enriching it here shows up in both, which is
 * the whole reason it is not written twice.
 *
 * Everything a visitor reads is a key of the "book" domain rather than a sentence, so a demo site seeded in
 * Spanish reads as a Spanish catalog. The two names are the exception: a person is not translated.
 */
class BookSampleCatalog
{
    // Made-up, and the same on every book: a catalog crediting four different authors for four books tells a story
    // about a publisher rather than about the bundle
    public const string AUTHOR = 'Camille Ferrand';
    public const string ILLUSTRATOR = 'Noé Berthier';

    // The locale the made-up books are written in, keyed like the rest so a Spanish demo holds Spanish books
    public const string LANGUAGE_KEY = 'label.book_sample_language';

    /**
     * Two series, which is what the "series" block prints - one alone would have it draw a single card and say
     * nothing of what a rail of them looks like.
     *
     * @return list<array{slug: string, title: string, summary: string}>
     */
    public function getSeries(): array
    {
        return [
            [
                'slug' => 'le-fil-rouge',
                'title' => 'label.book_sample_serie_thread_title',
                'summary' => 'label.book_sample_serie_thread_summary',
            ],
            [
                'slug' => 'carnets-d-atelier',
                'title' => 'label.book_sample_serie_workshop_title',
                'summary' => 'label.book_sample_serie_workshop_summary',
            ],
        ];
    }

    /**
     * Four books, two per serie, which is the fewest that shows what an index actually holds: three released, and
     * one still to come - the "to be published" rail has nothing to list otherwise.
     *
     * "published" is the date a book comes out, and the only thing telling the catalog from that rail (see
     * BookRepository::publishedQueryBuilder). Null on the fourth, which reads as "to be published" whatever the day
     * it is looked at - a written-down future date would quietly turn into a released book the year it came round.
     *
     * @return list<array{slug: string, title: string, summary: string, serie: string, published: ?string, creation: string, number: int, illustrated: bool}>
     */
    public function getBooks(): array
    {
        return [
            [
                'slug' => 'le-fil-rouge-1',
                'title' => 'label.book_sample_book_thread_one_title',
                'summary' => 'label.book_sample_book_thread_one_summary',
                'serie' => 'le-fil-rouge',
                'published' => '2025-09-18',
                'creation' => '2025-06-02',
                'number' => 1,
                'illustrated' => false,
            ],
            [
                'slug' => 'le-fil-rouge-2',
                'title' => 'label.book_sample_book_thread_two_title',
                'summary' => 'label.book_sample_book_thread_two_summary',
                'serie' => 'le-fil-rouge',
                'published' => '2026-02-12',
                'creation' => '2025-11-24',
                'number' => 2,
                'illustrated' => false,
            ],
            [
                // The one with an illustrator, so the credit line on a book's page has something to print
                'slug' => 'la-maison-du-quai',
                'title' => 'label.book_sample_book_quay_title',
                'summary' => 'label.book_sample_book_quay_summary',
                'serie' => 'carnets-d-atelier',
                'published' => '2024-11-05',
                'creation' => '2024-07-15',
                'number' => 1,
                'illustrated' => true,
            ],
            [
                'slug' => 'les-heures-claires',
                'title' => 'label.book_sample_book_hours_title',
                'summary' => 'label.book_sample_book_hours_summary',
                'serie' => 'carnets-d-atelier',
                'published' => null,
                'creation' => '2026-04-08',
                'number' => 2,
                'illustrated' => false,
            ],
        ];
    }
}

<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

// The made-up catalog this bundle stands behind, plain data read by GalleryShowcaseProvider as never-persisted entities and by BookDemoFixtureProvider as rows - one dataset, two readings, which is why it is not written twice. Everything a visitor reads is a key of the "book" domain, so a demo seeded in Spanish reads as a Spanish catalog; the two names are the exception, a person not being translated
class BookSampleCatalog
{
    // Made-up, and the same on every book: a catalog crediting four different authors for four books tells a story. about a publisher rather than about the bundle
    public const string AUTHOR = 'Camille Ferrand';
    public const string ILLUSTRATOR = 'Noé Berthier';

    // The locale the made-up books are written in, keyed like the rest so a Spanish demo holds Spanish books
    public const string LANGUAGE_KEY = 'label.book_sample_language';

    // Two series, which is what the "series" block prints - one alone draws a single card and says nothing of what a rail looks like
    /** @return list<array{slug: string, title: string, summary: string}> */
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

    // Three categories, which is what the "categories" block prints - a card each, enough to show the grid a listing draws rather than a single heading
    /** @return list<array{slug: string, title: string, summary: string}> */
    public function getCategories(): array
    {
        return [
            [
                'slug' => 'romans',
                'title' => 'label.book_sample_category_novels_title',
                'summary' => 'label.book_sample_category_novels_summary',
            ],
            [
                'slug' => 'jeunesse',
                'title' => 'label.book_sample_category_youth_title',
                'summary' => 'label.book_sample_category_youth_summary',
            ],
            [
                'slug' => 'carnets',
                'title' => 'label.book_sample_category_notebooks_title',
                'summary' => 'label.book_sample_category_notebooks_summary',
            ],
        ];
    }

    // Four books, two per serie: three released and one still to come, the "to be published" rail having nothing to list otherwise. "published" is what tells the catalog from that rail (see BookRepository::publishedQueryBuilder), null on the fourth so it stays to-be-published whatever the day - a written future date would quietly turn released. "age" is written as schema.org reads it and left untranslated, both forms shown, closed on the children's book and open on the novels
    /** @return list<array{slug: string, title: string, summary: string, serie: string, published: ?string, creation: string, number: int, illustrated: bool, age: string}> */
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
                'age' => '15-',
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
                'age' => '15-',
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
                'age' => '9-12',
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
                'age' => '15-',
            ],
        ];
    }
}

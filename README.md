# BookBundle

Symfony bundle that manages books and series for a publishing website — catalog, media collections, admin CRUD, pagination, and multilingual support.

[![GitHub](https://img.shields.io/github/license/975L/BookBundle)](https://github.com/975L/BookBundle/blob/master/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/c975l/book-bundle)](https://packagist.org/packages/c975l/book-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/c975l/book-bundle)](https://packagist.org/packages/c975l/book-bundle)

---

## Features

- Book and series catalog with paginated list and detail views
- Each book supports media, video, press, and marketing sub-collections with drag-and-drop ordering
- Series group books with sorted ordering
- Multilingual: books can reference translations across languages
- Admin CRUD via EasyAdmin for books and series
- Live component search for books
- Sitemap generation
- Twig `isbn` filter for formatting ISBN numbers

---

## Requirements

- PHP >= 8.0
- [c975L/UiBundle](https://github.com/975L/UiBundle)
- Doctrine ORM
- EasyAdmin
- KNP Paginator Bundle
- symfony/ux-live-component
- symfony/ux-twig-component
- VichUploader Bundle

---

## Installation

### Download

```bash
composer require c975l/book-bundle
```

### Run migrations

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Enable routes

Add the bundle routes to `config/routes.yaml`:

```yaml
c975_l_book:
    resource: "@c975LBookBundle/"
    type: attribute
    prefix: /
```

### Install assets

```bash
php bin/console assets:install --symlink
```

---

## Usage

### Routes

| Route | URL | Description |
| --- | --- | --- |
| `book_index` | `/livres` | Paginated book list |
| `book_display` | `/livre/{slug}` | Book detail page |
| `serie_index` | `/series` | Paginated series list |
| `serie_display` | `/serie/{slug}` | Series detail page |

### ISBN filter

Format a raw ISBN string in Twig:

```twig
{{ book.isbn|isbn }}
{# Outputs: 979-10-92030-14-3 #}
```

### Sitemap

Run the following command to generate `public/sitemap-books.xml`:

```bash
php bin/console book:sitemaps:create
```

---

If this project **helps you save development time**, consider sponsoring via the **Sponsor** button at the top of the GitHub page. Thank you!

<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Enum\BookContributorRole;
use c975L\BookBundle\Enum\BookSubjectScheme;
use c975L\BookBundle\Twig\BookSectionsExtension;
use c975L\UiBundle\Service\JsonLdBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Intl\Languages;

// The catalog as an ONIX 3.0 message, one <Product> per edition holding an ISBN - what a store or a script uploading to one reads instead of the pages. Built out of what the catalog already holds, as the JSON-LD is (see BookSnippetBuilder): nothing here is typed twice
class BookOnixBuilder
{
    // ISO 639-2/B, which ONIX asks for, where it differs from the 639-2/T Intl returns
    private const array BIBLIOGRAPHIC_LANGUAGES = [
        'bod' => 'tib', 'ces' => 'cze', 'cym' => 'wel', 'deu' => 'ger', 'ell' => 'gre', 'eus' => 'baq',
        'fas' => 'per', 'fra' => 'fre', 'hye' => 'arm', 'isl' => 'ice', 'kat' => 'geo', 'mkd' => 'mac',
        'mri' => 'mao', 'msa' => 'may', 'mya' => 'bur', 'nld' => 'dut', 'ron' => 'rum', 'slk' => 'slo',
        'sqi' => 'alb', 'zho' => 'chi',
    ];

    public function __construct(
        private readonly AudioDurationReader $durationReader,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
        private readonly JsonLdBuilder $jsonLdBuilder = new JsonLdBuilder(),
    ) {
    }

    // $baseUrl turns a stored file name into the address a store downloads the cover from; $sender names the publisher in the header and on each product
    /** @param Book[] $books */
    public function build(array $books, string $sender, string $baseUrl): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('ONIXMessage');
        $xml->writeAttribute('release', '3.0');
        $xml->writeAttribute('xmlns', 'http://ns.editeur.org/onix/3.0/reference');

        $xml->startElement('Header');
        $xml->startElement('Sender');
        $xml->writeElement('SenderName', $sender);
        $xml->endElement();
        $xml->writeElement('SentDateTime', gmdate('Ymd\THis\Z'));
        $xml->endElement();

        foreach ($books as $book) {
            foreach ($book->getEditions() as $edition) {
                if ('' !== $this->isbn($edition)) {
                    $this->product($xml, $book, $edition, $sender, rtrim($baseUrl, '/'));
                }
            }
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    // One edition, in the order the ONIX schema sets its blocks
    private function product(\XMLWriter $xml, Book $book, BookEdition $edition, string $sender, string $baseUrl): void
    {
        $isbn = $this->isbn($edition);
        $forthcoming = $book->getPublished() > new \DateTime();

        $xml->startElement('Product');
        $xml->writeElement('RecordReference', parse_url($baseUrl, \PHP_URL_HOST) . '.' . $isbn);
        $xml->writeElement('NotificationType', $forthcoming ? '02' : '03');
        $xml->startElement('ProductIdentifier');
        $xml->writeElement('ProductIDType', '15');
        $xml->writeElement('IDValue', $isbn);
        $xml->endElement();

        $this->descriptiveDetail($xml, $book, $edition);
        $this->collateralDetail($xml, $book, $baseUrl);
        $this->publishingDetail($xml, $book, $sender, $forthcoming);
        $this->productSupply($xml, $edition, $sender, $forthcoming);

        $xml->endElement();
    }

    // What the edition is: its form, its series, its title, who made it, its language, its extent, its subjects, its readers
    private function descriptiveDetail(\XMLWriter $xml, Book $book, BookEdition $edition): void
    {
        [$form, $detail] = self::productForm($edition);

        $xml->startElement('DescriptiveDetail');
        $xml->writeElement('ProductComposition', '00');
        $xml->writeElement('ProductForm', $form);
        if (null !== $detail) {
            $xml->writeElement('ProductFormDetail', $detail);
        }

        $serie = $book->getSerie();
        if (null !== $serie && '' !== trim((string) $serie->getTitle())) {
            $xml->startElement('Collection');
            $xml->writeElement('CollectionType', '10');
            $xml->startElement('TitleDetail');
            $xml->writeElement('TitleType', '01');
            $xml->startElement('TitleElement');
            $xml->writeElement('TitleElementLevel', '02');
            if (null !== $book->getNumber()) {
                $xml->writeElement('PartNumber', (string) $book->getNumber());
            }
            $xml->writeElement('TitleText', trim((string) $serie->getTitle()));
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
        } else {
            $xml->writeElement('NoCollection', null);
        }

        $xml->startElement('TitleDetail');
        $xml->writeElement('TitleType', '01');
        $xml->startElement('TitleElement');
        $xml->writeElement('TitleElementLevel', '01');
        $xml->writeElement('TitleText', trim((string) $book->getTitle()));
        $xml->endElement();
        $xml->endElement();

        $this->contributors($xml, $book, $edition);

        $language = self::language($book->getLanguage());
        if (null !== $language) {
            $xml->startElement('Language');
            $xml->writeElement('LanguageRole', '01');
            $xml->writeElement('LanguageCode', $language);
            $xml->endElement();
        }

        $this->extent($xml, $book, $edition);
        $this->subjects($xml, $book);
        $this->audienceRange($xml, $book->getAge());

        $xml->endElement();
    }

    // The author and the illustrator the book inherits from its serie, then its other credits - the voice only on the edition it reads
    private function contributors(\XMLWriter $xml, Book $book, BookEdition $edition): void
    {
        $credits = [['A01', $book->getEffectiveAuthor()], ['A12', $book->getEffectiveIllustrator()]];
        foreach ($book->getContributorsOf(BookContributorRole::Translator->value) as $translator) {
            $credits[] = ['B06', $translator];
        }
        if (str_contains((string) $edition->getKind(), 'audio')) {
            foreach ($book->getContributorsOf(BookContributorRole::Narrator->value) as $narrator) {
                $credits[] = ['E07', $narrator];
            }
        }

        $sequence = 0;
        foreach ($credits as [$role, $contributor]) {
            $name = $contributor instanceof Contributor ? trim((string) $contributor->getName()) : '';
            if ('' === $name) {
                continue;
            }

            $xml->startElement('Contributor');
            $xml->writeElement('SequenceNumber', (string) ++$sequence);
            $xml->writeElement('ContributorRole', $role);
            $xml->writeElement('PersonName', $name);
            $xml->endElement();
        }

        if (0 === $sequence) {
            $xml->writeElement('NoContributor', null);
        }
    }

    // The pages of a printed or digital edition, the playing time of a recorded one - read from its files, not typed
    private function extent(\XMLWriter $xml, Book $book, BookEdition $edition): void
    {
        $extents = [];
        if (str_contains((string) $edition->getKind(), 'audio')) {
            $seconds = $this->duration($book);
            if (null !== $seconds) {
                $extents[] = ['09', (string) $seconds, '06'];
            }
        } elseif (null !== $edition->getPages()) {
            $extents[] = ['00', (string) $edition->getPages(), '03'];
        }

        foreach ($extents as [$type, $value, $unit]) {
            $xml->startElement('Extent');
            $xml->writeElement('ExtentType', $type);
            $xml->writeElement('ExtentValue', $value);
            $xml->writeElement('ExtentUnit', $unit);
            $xml->endElement();
        }
    }

    // Every code of every category the book is filed under, each scheme's first subject flagged as the main one
    private function subjects(\XMLWriter $xml, Book $book): void
    {
        $written = [];
        foreach ($book->getCategories() as $category) {
            foreach (BookSubjectScheme::cases() as $scheme) {
                foreach ($category->getCodesOf($scheme) as $code) {
                    $onixScheme = $scheme->onixScheme($code);
                    if (isset($written[$onixScheme . $code])) {
                        continue;
                    }

                    $xml->startElement('Subject');
                    if (!\in_array($onixScheme, $written, true) && \in_array($onixScheme, ['10', '29', '93'], true)) {
                        $xml->writeElement('MainSubject', null);
                    }
                    $xml->writeElement('SubjectSchemeIdentifier', $onixScheme);
                    $xml->writeElement('SubjectCode', $code);
                    $xml->endElement();

                    $written[$onixScheme . $code] = $onixScheme;
                }
            }
        }
    }

    // The age the book says it is for - "3-8", "+16 ans", "18+" - as an interest age range in years
    private function audienceRange(\XMLWriter $xml, ?string $age): void
    {
        preg_match_all('/\d+/', (string) $age, $matches);
        $numbers = array_map(intval(...), $matches[0]);
        if ([] === $numbers) {
            return;
        }

        $xml->startElement('AudienceRange');
        $xml->writeElement('AudienceRangeQualifier', '17');
        $xml->writeElement('AudienceRangePrecision', '03');
        $xml->writeElement('AudienceRangeValue', (string) $numbers[0]);
        if (isset($numbers[1])) {
            $xml->writeElement('AudienceRangePrecision', '04');
            $xml->writeElement('AudienceRangeValue', (string) $numbers[1]);
        }
        $xml->endElement();
    }

    // The summary and the front cover
    private function collateralDetail(\XMLWriter $xml, Book $book, string $baseUrl): void
    {
        $summary = $this->jsonLdBuilder->plainText($book->getSummary());
        $cover = BookSectionsExtension::cover($book);
        if ('' === $summary && null === $cover) {
            return;
        }

        $xml->startElement('CollateralDetail');
        if ('' !== $summary) {
            $xml->startElement('TextContent');
            $xml->writeElement('TextType', '03');
            $xml->writeElement('ContentAudience', '00');
            $xml->writeElement('Text', $summary);
            $xml->endElement();
        }
        if (null !== $cover) {
            $xml->startElement('SupportingResource');
            $xml->writeElement('ResourceContentType', '01');
            $xml->writeElement('ContentAudience', '00');
            $xml->writeElement('ResourceMode', '03');
            $xml->startElement('ResourceVersion');
            $xml->writeElement('ResourceForm', '02');
            $xml->writeElement('ResourceLink', $baseUrl . '/' . ltrim((string) $cover->getName(), '/'));
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
    }

    // Who publishes it, and when it comes out - a date still ahead making it forthcoming
    private function publishingDetail(\XMLWriter $xml, Book $book, string $sender, bool $forthcoming): void
    {
        $xml->startElement('PublishingDetail');
        $xml->startElement('Publisher');
        $xml->writeElement('PublishingRole', '01');
        $xml->writeElement('PublisherName', $sender);
        $xml->endElement();
        $xml->writeElement('PublishingStatus', $forthcoming ? '02' : '04');
        $xml->startElement('PublishingDate');
        $xml->writeElement('PublishingDateRole', '01');
        $xml->writeElement('Date', (string) $book->getPublished()?->format('Ymd'));
        $xml->endElement();
        $xml->endElement();
    }

    // Sold everywhere, by the publisher, at the edition's price tax included when it has one - without one, a price still to come for a forthcoming book, a store to ask the publisher otherwise
    private function productSupply(\XMLWriter $xml, BookEdition $edition, string $sender, bool $forthcoming): void
    {
        $xml->startElement('ProductSupply');
        $xml->startElement('Market');
        $xml->startElement('Territory');
        $xml->writeElement('RegionsIncluded', 'WORLD');
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('SupplyDetail');
        $xml->startElement('Supplier');
        $xml->writeElement('SupplierRole', '01');
        $xml->writeElement('SupplierName', $sender);
        $xml->endElement();
        $xml->writeElement('ProductAvailability', $forthcoming ? '10' : '20');
        if (null !== $edition->getPrice()) {
            $xml->startElement('Price');
            $xml->writeElement('PriceType', '02');
            $xml->writeElement('PriceAmount', number_format($edition->getPrice() / 100, 2, '.', ''));
            $xml->writeElement('CurrencyCode', $edition->getCurrency());
            $xml->endElement();
        } else {
            $xml->writeElement('UnpricedItemType', $forthcoming ? '02' : '04');
        }
        $xml->endElement();
        $xml->endElement();
    }

    // The playing time of all the book's recordings together, null when none can be read
    private function duration(Book $book): ?int
    {
        $total = null;
        foreach ($book->getAudios() as $audio) {
            $seconds = $this->durationReader->seconds($this->publicDir . '/' . ltrim((string) $audio->getName(), '/'));
            if (null !== $seconds) {
                $total = ($total ?? 0) + $seconds;
            }
        }

        return $total;
    }

    // The ONIX form and its detail, matched on the site's own word for the kind as the JSON-LD does (see BookSnippetBuilder::BOOK_FORMATS): "paper" is a paperback, "audio" an MP3 download, anything else a download whose format says PDF or EPUB
    /** @return array{string, string|null} */
    private static function productForm(BookEdition $edition): array
    {
        $kind = (string) $edition->getKind();

        return match (true) {
            str_contains($kind, 'paper') => ['BC', null],
            str_contains($kind, 'audio') => ['AJ', 'A103'],
            str_contains(strtolower((string) $edition->getFormat()), 'pdf') => ['ED', 'E107'],
            default => ['ED', 'E101'],
        };
    }

    // The book's language as ONIX writes it, from "fr" or "fr_FR"
    private static function language(?string $language): ?string
    {
        $code = strtolower(substr(trim((string) $language), 0, 2));
        if ('' === $code || !Languages::exists($code)) {
            return null;
        }

        $alpha3 = Languages::getAlpha3Code($code);

        return self::BIBLIOGRAPHIC_LANGUAGES[$alpha3] ?? $alpha3;
    }

    // The ISBN as ONIX takes it, 13 digits only - an older ISBN-10 is left out rather than sent truncated
    private function isbn(BookEdition $edition): string
    {
        $digits = preg_replace('/\D/', '', (string) $edition->getIsbn()) ?? '';

        return 13 === \strlen($digits) ? $digits : '';
    }
}

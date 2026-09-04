<?php

namespace c975L\BookBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Twig\Attribute\AsTwigFilter;

// An ISBN as it is printed on the book. Where the number is cut is not deducible from the number itself: the length of the registration group and of the registrant come from the ranges the International ISBN Agency publishes, and under one prefix they vary - a publisher owning "978-2-9598005" and "978-2-488750" is cut at the eleventh digit on one and at the tenth on the other. So the site declares its own roots rather than this bundle carrying the whole range table (see config/configs.json)
class IsbnExtension
{
    // The roots the publisher was granted, one per line and hyphenated as they are printed
    private const string PREFIXES_CONFIG = 'book-isbn-prefixes';

    public function __construct(private readonly ConfigServiceInterface $configService)
    {
    }

    // The root the number starts with, then its publication and its check digit. A number matching no declared root keeps its digits and no hyphen at all: hyphens laid in the wrong place read as another publisher's ISBN, which is worse than none
    #[AsTwigFilter('isbn')]
    public function isbnFilter(mixed $data): string
    {
        $digits = self::digits((string) $data);
        $root = $this->root($digits);

        if (null === $root) {
            return $digits;
        }

        $rest = substr($digits, \strlen(self::digits($root)));

        return $root . '-' . substr($rest, 0, -1) . '-' . substr($rest, -1);
    }

    // The longest declared root the number starts with: a publisher holds one per range it was granted, and the longest of those matching is the one actually naming it. A root leaving no room for a publication and a check digit names another number, not this one
    private function root(string $digits): ?string
    {
        $declared = (string) $this->configService->get(self::PREFIXES_CONFIG);
        $found = null;

        foreach (preg_split('/[\r\n,;]+/', $declared) ?: [] as $root) {
            $root = trim($root);
            $length = \strlen(self::digits($root));

            if (0 === $length || $length > \strlen($digits) - 2 || !str_starts_with($digits, self::digits($root))) {
                continue;
            }

            if (null === $found || $length > \strlen(self::digits($found))) {
                $found = $root;
            }
        }

        return $found;
    }

    // A number read through its digits alone, an ISBN being stored bare and declared hyphenated
    private static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}

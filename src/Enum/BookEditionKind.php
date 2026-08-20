<?php

namespace c975L\BookBundle\Enum;

// The editions a book is published in, used as the default vocabulary when no site declares its own (see c975L\BookBundle\Contract\BookCustomizationProviderInterface). A kind is stored as a plain string on c975L\BookBundle\Entity\BookEdition, so a site publishing an illustrated or a translated edition names it without the bundle having to know it
enum BookEditionKind: string
{
    case Paper = 'paper';
    case Digital = 'digital';
    case Audio = 'audio';

    // The translation key of the edition's name, in the "book" domain
    public function label(): string
    {
        return 'label.edition_' . $this->value;
    }

    /** @return array<string, string> kind => label */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::cases() as $case) {
            $defaults[$case->value] = $case->label();
        }

        return $defaults;
    }
}

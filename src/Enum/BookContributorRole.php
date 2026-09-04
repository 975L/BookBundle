<?php

namespace c975L\BookBundle\Enum;

// The parts someone takes in a book beyond signing or drawing it, used as the default vocabulary when no site declares its own (see c975L\BookBundle\Contract\BookCustomizationProviderInterface). A role is stored as a plain string on c975L\BookBundle\Entity\BookContributor, so a catalog crediting a colourist, a letterer or a preface writer names it without the bundle having to know it. Neither author nor illustrator: those two are the book's own columns, inherited from its serie when it names none (see Book::getEffectiveAuthor()), and a list offering them beside the fields holding them would have an editor credit the same person twice
enum BookContributorRole: string
{
    case Narrator = 'narrator';
    case Translator = 'translator';

    // The translation key of the role's name, in the "book" domain
    public function label(): string
    {
        return 'label.role_' . $this->value;
    }

    /** @return array<string, string> role => label */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::cases() as $case) {
            $defaults[$case->value] = $case->label();
        }

        return $defaults;
    }
}

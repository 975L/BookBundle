<?php

namespace c975L\BookBundle\Enum;

// What a serie tells, which decides where it is listed: a serie of books answers to the books' index, a serie of planches to the planches' one (see SerieController::index() and StripController::index()). The two indexes share no serie, so no listing repeats another
// Stored as a plain string on c975L\BookBundle\Entity\Serie and left null on sites that never filled it in: a serie without a kind is then filed by what it actually holds, which is what the repository falls back on (see SerieRepository::findWithBooks())
enum SerieKind: string
{
    case Book = 'book';
    case Strip = 'strip';

    // The translation key of the kind's name, in the "book" domain
    public function label(): string
    {
        return 'label.' . $this->value;
    }
}

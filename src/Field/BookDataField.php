<?php

namespace c975L\BookBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

// The fields a site adds to a book, rendered from the form type it declares (see c975L\BookBundle\Contract\BookCustomizationProviderInterface). A field of its own rather than one of EasyAdmin's: an untyped field resolves off the Doctrine type, and a json column resolves to ArrayField, whose configurator hands the form type collection options ("allow_add", "entry_type"…) a plain form knows nothing of - TextField's own refuses the array outright
final class BookDataField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, $label = null): self
    {
        return new self()
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('@c975LBook/management/field_data.html.twig')
            ->hideOnIndex()
        ;
    }
}

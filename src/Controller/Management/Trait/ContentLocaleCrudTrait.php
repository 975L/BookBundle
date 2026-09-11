<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Controller\Management\Trait;

use c975L\BookBundle\Contract\TrashableInterface;
use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookCategory;
use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Service\BookTranslator;
use c975L\ConfigBundle\Management\ContentLocaleScreen;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Locales;

use function Symfony\Component\Translation\t;

// The same edit screen opened on another language, written once for the six screens holding prose - which texts it carries is BookTranslator::fields(), each one unmapped so it never overwrites the text the row was written in
trait ContentLocaleCrudTrait
{
    // Subscribed rather than injected, so the six screens using this trait do not each carry its collaborators in their constructor - EasyAdmin already subscribes the admin context
    #[\Override]
    public static function getSubscribedServices(): array
    {
        return [
            ...parent::getSubscribedServices(),
            BookTranslator::class => BookTranslator::class,
            ContentLocaleScreen::class => ContentLocaleScreen::class,
        ];
    }

    // The collaborators read off that locator
    private function adminContextProvider(): AdminContextProviderInterface
    {
        return $this->container->get(AdminContextProviderInterface::class);
    }

    private function bookTranslator(): BookTranslator
    {
        return $this->container->get(BookTranslator::class);
    }

    private function contentLocaleScreen(): ContentLocaleScreen
    {
        return $this->container->get(ContentLocaleScreen::class);
    }

    // The language this row is being written in, when it is not the one the site was written in (see ContentLocaleScreen)
    private function contentLocale(): ?string
    {
        return $this->contentLocaleScreen()->locale($this->bookTranslator()->getTranslatableLocales());
    }

    // The row the screen is open on, and null on a "new" screen or on anything this catalog does not translate
    private function translatableRow(): Book | BookCategory | Character | Contributor | Serie | Strip | null
    {
        $entity = $this->adminContextProvider()->getContext()?->getEntity()?->getInstance();

        return null === $entity ? null : $this->asTranslatableRow($entity);
    }

    // The same reading of one entity, for the form's own submission: what a screen hands over is the row it was open on, and this is what says so in a type the translator accepts
    private function asTranslatableRow(object $entity): Book | BookCategory | Character | Contributor | Serie | Strip | null
    {
        return $entity instanceof Book
            || $entity instanceof Serie
            || $entity instanceof BookCategory
            || $entity instanceof Strip
            || $entity instanceof Character
            || $entity instanceof Contributor
            ? $entity
            : null;
    }

    // What a language screen offers: each translatable text, holding what that language already says or the source text between brackets where it says nothing yet
    /** @return list<FieldInterface> */
    private function translationFields(string $locale): array
    {
        $row = $this->translatableRow();
        if (null === $row) {
            return [];
        }

        $values = $this->bookTranslator()->promptValues($row, $locale);

        $fields = [
            FormField::addFieldset(t('label.fieldset_this_language', ['%language%' => Locales::getName($locale, $locale)], 'book'))
                ->setHelp(t('label.fieldset_this_language_help', [], 'book')),
        ];

        foreach ($this->bookTranslator()->fields($row) as $field) {
            // A title or a name is a line, and the one a listing, a card and a <title> read; everything else is prose - a person's presentation included, which is the only text they carry (see BookTranslator::LINE_FIELDS)
            $fields[] = \in_array($field, BookTranslator::LINE_FIELDS, true)
                ? TextField::new($field)
                    ->setLabel(t('label.' . $field, [], 'book'))
                    ->setRequired(false)
                    ->setFormTypeOption('mapped', false)
                    ->setFormTypeOption('data', $values[$field])
                : TextareaField::new($field)
                    ->setLabel(t('label.' . $field, [], 'book'))
                    ->setRequired(false)
                    ->setFormTypeOption('mapped', false)
                    ->setFormTypeOption('data', $values[$field])
                    // Opt-in marker read by the block form theme, which is what puts Donovan under a plain textarea
                    ->setFormTypeOption('attr', ['data-ai-rephrase' => true]);
        }

        return $fields;
    }

    // Opens the first language screen straight from the list, the way SiteBundle's pages and ShopBundle's products are translated - the tabs above a row already opened are the only other way in, and a translation screen nobody finds translates nothing
    private function translateAction(): Action
    {
        return $this->contentLocaleScreen()
            ->action('translate', t('action.translate', [], 'book'), 'fa fa-language', $this->bookTranslator()->getTranslatableLocales())
            ->displayIf(fn (object $entity): bool => $this->bookTranslator()->isActive() && !($entity instanceof TrashableInterface && $entity->isDeleted()))
            ->addCssClass('btn btn-secondary');
    }

    // What the language tabs at the top of the edit screen need, and nothing at all where the row is not saved yet or the site declares a single language
    #[\Override]
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);

        $id = $this->translatableRow()?->getId();
        if (null !== $id && $this->bookTranslator()->isActive()) {
            $this->contentLocaleScreen()->addParameters($responseParameters, self::class, $id, $this->bookTranslator()->getTranslatableLocales(), $this->contentLocale());
        }

        return $responseParameters;
    }

    // What a language screen wrote, handed over to be stored on the flush that saves the row and never before it (see ContentLocaleScreen::stageOnSubmit)
    #[\Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        $contentLocale = $this->contentLocale();
        $row = $this->translatableRow();

        $this->contentLocaleScreen()->stageOnSubmit(
            $formBuilder,
            $contentLocale,
            null === $row ? [] : $this->bookTranslator()->fields($row),
            function (object $entity, array $values) use ($contentLocale): void {
                $row = $this->asTranslatableRow($entity);
                if (null !== $contentLocale && null !== $row) {
                    $this->bookTranslator()->stage($row, $contentLocale, $values);
                }
            }
        );

        return $formBuilder;
    }
}

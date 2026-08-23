<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Form\BookEditionType;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookEditionTypeTest extends TestCase
{
    // No provider declared, which is every site not naming its own editions - the form then offers the bundle's own
    private static function type(): BookEditionType
    {
        return new BookEditionType(new BookCustomizationRegistry([], self::registryTranslator()));
    }

    /** @return array<string, array{type: ?string, options: array<string, mixed>}> */
    private function build(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        self::type()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAsksForWhatOneEditionHolds(): void
    {
        $added = $this->build();

        $this->assertSame(['position', 'kind', 'isbn', 'pages', 'format'], array_keys($added));
        $this->assertSame(HiddenType::class, $added['position']['type']);
        $this->assertSame(ChoiceType::class, $added['kind']['type']);
        $this->assertSame(TextType::class, $added['isbn']['type']);
        $this->assertSame(IntegerType::class, $added['pages']['type']);
        $this->assertSame(TextType::class, $added['format']['type']);
    }

    // Neither files nor platforms: they belong to the book and are edited under the gesture they serve - the recording under "Listen" with the podcast apps, the bookshops under "Buy" (see BookCrudController). An edition says only what the book comes out under
    public function testAFormatHoldsNeitherFilesNorPlatforms(): void
    {
        $added = $this->build();

        $this->assertArrayNotHasKey('medias', $added);
        $this->assertArrayNotHasKey('links', $added);
    }

    // An edition kind is the site's own word, offered under the label its vocabulary gives it
    public function testTheKindIsOfferedUnderTheLabelTheVocabularyGivesIt(): void
    {
        $choices = $this->build()['kind']['options']['choices'];

        $this->assertSame('paper', $choices['label.edition_paper']);
    }

    // Left empty for an edition whose ISBN is reserved but not yet used. The date is no longer asked here: the book carries its own, and it is the only one (see BookEdition)
    public function testAFormatIsSavedWithoutAnIsbn(): void
    {
        $this->assertFalse($this->build()['isbn']['options']['required']);
    }

    public function testTheHiddenPositionCarriesTheClassTheSortingHangsOn(): void
    {
        $this->assertSame('ui-sort-position', $this->build()['position']['options']['attr']['class']);
    }

    public function testConfigureOptionsBindsTheEdition(): void
    {
        $resolver = new OptionsResolver();
        self::type()->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertSame(BookEdition::class, $options['data_class']);
        $this->assertSame('book', $options['translation_domain']);
    }

    // The translator the registry asks for: it returns the key as is, which the real one does for a brand - a label that is no translation key is not translated
    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}

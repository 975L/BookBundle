<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookEdition;
use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Form\BookEditionType;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
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

        $this->assertSame(['position', 'kind', 'isbn', 'pages', 'format', 'price', 'currency', 'channels'], array_keys($added));
        $this->assertSame(100, $added['price']['options']['divisor']);
        $this->assertSame(HiddenType::class, $added['position']['type']);
        $this->assertSame(ChoiceType::class, $added['kind']['type']);
        $this->assertSame(TextType::class, $added['isbn']['type']);
        $this->assertSame(IntegerType::class, $added['pages']['type']);
        $this->assertSame(TextType::class, $added['format']['type']);
    }

    /** @return array<string, callable> */
    private function listeners(): array
    {
        $listeners = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')->willReturnCallback(function (string $event, callable $callback) use (&$listeners, $builder) {
            $listeners[$event] = $callback;

            return $builder;
        });
        self::type()->buildForm($builder, []);

        return $listeners;
    }

    // The slots of the files follow what the edition is: none for a printed book, the MP3 for a recording, the three files of an ebook, the four while the kind is not chosen yet
    #[DataProvider('fileSlots')]
    public function testTheFileSlotsFollowTheKindOfTheEdition(?string $kind, array $expected): void
    {
        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name) use (&$added, $form) {
            $added[] = $name;

            return $form;
        });
        $this->listeners()[FormEvents::PRE_SET_DATA](new FormEvent($form, null === $kind ? null : new BookEdition()->setKind($kind)));

        $this->assertSame($expected, $added);
    }

    public static function fileSlots(): iterable
    {
        yield 'paper' => ['paper', []];
        yield 'audio' => ['audio', ['file_audio', 'file_cover_front']];
        yield 'digital' => ['digital', ['file_epub', 'file_pdf', 'file_booklet', 'file_cover_front', 'file_cover_back']];
        yield 'new edition' => [null, ['file_epub', 'file_pdf', 'file_booklet', 'file_audio', 'file_cover_front', 'file_cover_back']];
    }

    // A slot is edited by reference: a clone of the stored file would be a new row to Doctrine, inserted over the one it copies and refused by the unique name
    public function testTheFileSlotsAreEditedByReference(): void
    {
        $options = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $opts = []) use (&$options, $form) {
            $options[$name] = $opts;

            return $form;
        });
        $this->listeners()[FormEvents::PRE_SET_DATA](new FormEvent($form, new BookEdition()->setKind('digital')));

        $this->assertArrayNotHasKey('by_reference', $options['file_epub']);
    }

    // The stored file coming back is kept as the very same object, and one whose upload was deleted with its checkbox leaves the edition
    public function testTheSubmittedSlotsAreHandedBackToTheEdition(): void
    {
        $epub = new BookEditionFile()->setName('medias/book/editions/a.epub');
        $pdf = new BookEditionFile()->setName('medias/book/editions/a.pdf');
        $edition = new BookEdition()->setKind('digital')->setFileOf(BookEditionFileKind::Epub, $epub)->setFileOf(BookEditionFileKind::Pdf, $pdf);
        $pdf->setName(null);

        $children = ['file_epub' => $epub, 'file_pdf' => $pdf];
        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturnCallback(fn (string $name): bool => array_key_exists($name, $children));
        $form->method('get')->willReturnCallback(function (string $name) use ($children) {
            $child = $this->createStub(FormInterface::class);
            $child->method('getData')->willReturn($children[$name]);

            return $child;
        });
        $this->listeners()[FormEvents::POST_SUBMIT](new FormEvent($form, $edition));

        $this->assertSame($epub, $edition->getFileOf(BookEditionFileKind::Epub));
        $this->assertNull($edition->getFileOf(BookEditionFileKind::Pdf));
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

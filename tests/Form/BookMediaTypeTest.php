<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Form\BookMediaType;
use c975L\BookBundle\Service\BookCustomizationRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Form\Type\VichImageType;

class BookMediaTypeTest extends TestCase
{
    /** @var array<string, array{type: ?string, options: array<string, mixed>}> */
    private array $added = [];

    /** @var array<string, callable> */
    private array $listeners = [];

    /** @param array<string, string> $kinds */
    private function build(array $kinds = []): void
    {
        $this->added = [];
        $this->listeners = [];

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$builder) {
            $this->added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });
        $builder->method('addEventListener')->willReturnCallback(function (string $event, callable $listener) use (&$builder) {
            $this->listeners[$event] = $listener;

            return $builder;
        });

        $provider = $this->createStub(BookCustomizationProviderInterface::class);
        $provider->method('getMediaKinds')->willReturn($kinds);

        new BookMediaType(new BookCustomizationRegistry([] === $kinds ? [] : [$provider], self::registryTranslator()))->buildForm($builder, []);
    }

    // The type served to the "file" field once the row's own data is known - what the PRE_SET_DATA listener re-adds it as
    private function fileTypeFor(?BookMedia $media): ?string
    {
        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $form) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $form;
        });

        ($this->listeners[FormEvents::PRE_SET_DATA])(new PreSetDataEvent($form, $media));

        return $added['file']['type'] ?? null;
    }

    public function testBuildFormAsksForWhatOneFileHolds(): void
    {
        $this->build();

        // The kind is always asked: a site naming none is offered the bundle's own (see BookMediaKind)
        $this->assertSame(['position', 'file', 'kind'], array_keys($this->added));
        $this->assertSame(HiddenType::class, $this->added['position']['type']);
        $this->assertSame('label.media', $this->added['file']['options']['label']);
        $this->assertTrue($this->added['file']['options']['allow_delete']);
        $this->assertSame(ChoiceType::class, $this->added['kind']['type']);
    }

    // A site naming its own kinds is asked which one the file is, out of its own vocabulary and not the bundle's
    public function testKindIsAskedForOutOfTheSitesOwnVocabulary(): void
    {
        $this->build(['pages' => 'Pages']);

        $this->assertSame(['position', 'file', 'kind'], array_keys($this->added));
        $this->assertSame(ChoiceType::class, $this->added['kind']['type']);
    }

    public function testAnImageIsAskedForAsAnImageSoItsPreviewShows(): void
    {
        $this->build();

        $media = new BookMedia()->setName('couverture.jpg');

        $this->assertSame(VichImageType::class, $this->fileTypeFor($media));
    }

    public function testAnythingElseStaysAPlainFile(): void
    {
        $this->build();

        $media = new BookMedia()->setName('extrait.pdf');

        $this->assertSame(VichFileType::class, $this->fileTypeFor($media));
    }

    // A row just added holds no file yet, so nothing says it will be an image
    public function testANewRowStaysAPlainFile(): void
    {
        $this->build();

        $this->assertSame(VichFileType::class, $this->fileTypeFor(null));
    }

    // The translator the registry asks for: it returns the key as is, which the real one does for a brand - a label that is no translation key is not translated
    private static function registryTranslator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}

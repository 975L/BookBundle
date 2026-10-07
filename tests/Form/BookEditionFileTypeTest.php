<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookEditionFile;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Form\BookEditionFileType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Form\Type\VichImageType;

class BookEditionFileTypeTest extends TestCase
{
    // The file, labelled and filtered by its kind, linked through the admin route under its own name, and its price in cents
    public function testTheSlotAsksForTheFileOfItsKindAndItsPrice(): void
    {
        $added = $this->build(BookEditionFileKind::Audio);

        $this->assertSame(['file', 'price'], array_keys($added));
        $this->assertSame(VichFileType::class, $added['file']['type']);
        $this->assertSame('label.edition_file_audio', $added['file']['options']['label']);
        $this->assertSame(['mp3', 'm4a'], $added['file']['options']['constraints'][0]->extensions);
        $this->assertSame(100, $added['price']['options']['divisor']);

        $file = $this->file('medias/editions/book-audio/recording.mp3');
        $this->assertSame('/management/book/edition-file?id=7', $added['file']['options']['download_uri']($file));
        $this->assertSame('recording.mp3', $added['file']['options']['download_label']($file)['download_label']);
    }

    // A cover is shown as its preview, served by the same route, and has no price
    public function testACoverIsPreviewedThroughTheAdminRoute(): void
    {
        $added = $this->build(BookEditionFileKind::CoverFront);

        $this->assertSame(['file'], array_keys($added));
        $this->assertSame(VichImageType::class, $added['file']['type']);
        $this->assertSame('/management/book/edition-file?id=7', $added['file']['options']['image_uri']($this->file('cover.jpg')));
    }

    public function testTheKindIsRequired(): void
    {
        $resolver = new OptionsResolver();
        new BookEditionFileType($this->createStub(UrlGeneratorInterface::class))->configureOptions($resolver);

        $this->assertSame(BookEditionFile::class, $resolver->resolve(['kind' => BookEditionFileKind::Epub])['data_class']);
        $this->expectException(MissingOptionsException::class);
        $resolver->resolve();
    }

    // The fields the type adds for a kind, by name
    private function build(BookEditionFileKind $kind): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(fn (string $route, array $parameters) => '/management/book/edition-file?id=' . $parameters['id']);

        new BookEditionFileType($urlGenerator)->buildForm($builder, ['kind' => $kind]);

        return $added;
    }

    // A stored file of the given name, id 7
    private function file(string $name): BookEditionFile
    {
        $file = new BookEditionFile()->setName($name);
        new \ReflectionProperty(Media::class, 'id')->setValue($file, 7);

        return $file;
    }
}

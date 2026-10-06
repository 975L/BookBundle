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
use c975L\BookBundle\Enum\BookEditionFileKind;
use c975L\BookBundle\Form\BookEditionFileType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;

class BookEditionFileTypeTest extends TestCase
{
    // The file, labelled and filtered by its kind, never offered for download, and its price in cents
    public function testTheSlotAsksForTheFileOfItsKindAndItsPrice(): void
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new BookEditionFileType()->buildForm($builder, ['kind' => BookEditionFileKind::Audio]);

        $this->assertSame(['file', 'price'], array_keys($added));
        $this->assertSame(VichFileType::class, $added['file']['type']);
        $this->assertSame('label.edition_file_audio', $added['file']['options']['label']);
        $this->assertFalse($added['file']['options']['download_uri']);
        $this->assertSame(['mp3', 'm4a'], $added['file']['options']['constraints'][0]->extensions);
        $this->assertSame(100, $added['price']['options']['divisor']);
    }

    public function testTheKindIsRequired(): void
    {
        $resolver = new OptionsResolver();
        new BookEditionFileType()->configureOptions($resolver);

        $this->assertSame(BookEditionFile::class, $resolver->resolve(['kind' => BookEditionFileKind::Epub])['data_class']);
        $this->expectException(MissingOptionsException::class);
        $resolver->resolve();
    }
}

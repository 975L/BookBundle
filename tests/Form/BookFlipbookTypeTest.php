<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Form\BookFlipbookType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;

// The flipbook row: a video and not an image, hence a plain file field where BookCoverType asks for an image one - EasyAdmin's image widget can show nothing of an mp4
class BookFlipbookTypeTest extends TestCase
{
    /** @var array<string, array{type: ?string, options: array<string, mixed>}> */
    private array $added = [];

    public function testTheRowAsksForItsPositionAndItsFileAndNothingElse(): void
    {
        $this->assertSame(['position', 'file'], $this->build());
        $this->assertSame(HiddenType::class, $this->added['position']['type']);
        $this->assertSame(VichFileType::class, $this->added['file']['type']);
    }

    // An mp4 and nothing else: the message names the help key rather than listing the type twice
    public function testOnlyAnMp4IsAccepted(): void
    {
        $this->build();
        $constraints = $this->added['file']['options']['constraints'];

        $this->assertCount(1, $constraints);
        $this->assertInstanceOf(File::class, $constraints[0]);
        $this->assertSame(['video/mp4'], $constraints[0]->mimeTypes);
        $this->assertSame('label.flipbook-help', $constraints[0]->mimeTypesMessage);
    }

    public function testTheRowsEntityAndDomainAreTheBooksOwn(): void
    {
        $resolver = new OptionsResolver();
        new BookFlipbookType()->configureOptions($resolver);
        $options = $resolver->resolve([]);

        $this->assertSame(BookMedia::class, $options['data_class']);
        $this->assertSame('book', $options['translation_domain']);
    }

    /** @return list<string> */
    private function build(): array
    {
        $this->added = [];

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $formType = null, array $options = []) use (&$builder) {
            $this->added[$name] = ['type' => $formType, 'options' => $options];

            return $builder;
        });
        $builder->method('addEventListener')->willReturnCallback(static fn (): FormBuilderInterface => $builder);

        new BookFlipbookType()->buildForm($builder, []);

        return array_keys($this->added);
    }
}

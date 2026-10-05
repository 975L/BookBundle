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
use c975L\BookBundle\Form\BookCuesType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;

// The timecodes row: a WebVTT file, checked on its extension since whatever sniffs its content reads plain text
class BookCuesTypeTest extends TestCase
{
    /** @var array<string, array{type: ?string, options: array<string, mixed>}> */
    private array $added = [];

    public function testTheRowAsksForItsPositionAndItsFileAndNothingElse(): void
    {
        $this->assertSame(['position', 'file'], $this->build());
        $this->assertSame(HiddenType::class, $this->added['position']['type']);
        $this->assertSame(VichFileType::class, $this->added['file']['type']);
    }

    // A .vtt and nothing else, read as text/vtt or as the text/plain a server sniffs it as
    public function testOnlyAVttIsAccepted(): void
    {
        $this->build();
        $constraints = $this->added['file']['options']['constraints'];

        $this->assertCount(1, $constraints);
        $this->assertInstanceOf(File::class, $constraints[0]);
        $this->assertSame(['vtt' => ['text/vtt', 'text/plain']], $constraints[0]->extensions);
        $this->assertSame('label.cues-help', $constraints[0]->extensionsMessage);
    }

    public function testTheRowsEntityAndDomainAreTheBooksOwn(): void
    {
        $resolver = new OptionsResolver();
        new BookCuesType()->configureOptions($resolver);
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

        new BookCuesType()->buildForm($builder, []);

        return array_keys($this->added);
    }
}

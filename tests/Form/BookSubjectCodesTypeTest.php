<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Enum\BookSubjectScheme;
use c975L\BookBundle\Form\BookSubjectCodesType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The codes of a category: one optional text field per trade classification, named after the scheme
class BookSubjectCodesTypeTest extends TestCase
{
    /** @var array<string, array{type: ?string, options: array<string, mixed>}> */
    private array $added = [];

    // A scheme added to the enum gets its field without the form being touched
    public function testEachSchemeIsAnOptionalTextField(): void
    {
        $this->assertSame(array_column(BookSubjectScheme::cases(), 'value'), $this->build());

        foreach (BookSubjectScheme::cases() as $scheme) {
            $field = $this->added[$scheme->value];
            $this->assertSame(TextType::class, $field['type']);
            $this->assertSame($scheme->label(), $field['options']['label']);
            $this->assertSame($scheme->label() . '-help', $field['options']['help']);
            $this->assertFalse($field['options']['required']);
        }
    }

    public function testTheDomainIsTheBooksOwn(): void
    {
        $resolver = new OptionsResolver();
        new BookSubjectCodesType()->configureOptions($resolver);

        $this->assertSame('book', $resolver->resolve([])['translation_domain']);
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

        new BookSubjectCodesType()->buildForm($builder, []);

        return array_keys($this->added);
    }
}

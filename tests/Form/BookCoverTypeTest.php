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
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Form\BookCoverType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichImageType;

// The row of the three fields a book's own images are uploaded on: no kind is asked for, the field a file is dropped on being what says which of the three it is
class BookCoverTypeTest extends TestCase
{
    /** @var array<string, array{type: ?string, options: array<string, mixed>}> */
    private array $added = [];

    /** @var list<callable> */
    private array $listeners = [];

    // Only what belongs to the row: its rank in the collection and the file itself
    public function testTheRowAsksForItsPositionAndItsFileAndNothingElse(): void
    {
        $this->assertSame(['position', 'file'], $this->build());
        $this->assertSame(HiddenType::class, $this->added['position']['type']);
        $this->assertSame(VichImageType::class, $this->added['file']['type']);
    }

    // An image and never anything else, so EasyAdmin renders the thumbnail of what is already there
    public function testTheFileIsOfferedAsAnImageAndCappedAtOneHundredMegabytes(): void
    {
        $this->build();
        $constraints = $this->added['file']['options']['constraints'];

        $this->assertCount(1, $constraints);
        $this->assertInstanceOf(File::class, $constraints[0]);
        $this->assertSame(100000000, $constraints[0]->maxSize);
        $this->assertTrue($this->added['file']['options']['allow_delete']);
    }

    public function testTheRowsEntityAndDomainAreTheBooksOwn(): void
    {
        $resolver = new OptionsResolver();
        new BookCoverType()->configureOptions($resolver);
        $options = $resolver->resolve([]);

        $this->assertSame(BookMedia::class, $options['data_class']);
        $this->assertSame('book', $options['translation_domain']);
    }

    // The hidden id is what a drag from one collection to another posts: a saved row carries it
    public function testASavedRowCarriesTheHiddenIdTheMoveIsPostedWith(): void
    {
        $media = new BookMedia();
        new \ReflectionProperty(Media::class, 'id')->setValue($media, 12);

        $this->assertSame(['id' => ['mapped' => false, 'data' => '12']], $this->idFieldFor($media));
    }

    // A row just added has nothing to move yet, so it stays out of the mechanism
    public function testARowWithNoIdYetIsLeftOutOfTheMoveMechanism(): void
    {
        $this->assertSame([], $this->idFieldFor(new BookMedia()));
    }

    /** @return list<string> */
    private function build(): array
    {
        $this->added = [];
        $this->listeners = [];

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $formType = null, array $options = []) use (&$builder) {
            $this->added[$name] = ['type' => $formType, 'options' => $options];

            return $builder;
        });
        $builder->method('addEventListener')->willReturnCallback(function (string $event, callable $listener) use (&$builder) {
            $this->listeners[] = $listener;

            return $builder;
        });

        new BookCoverType()->buildForm($builder, []);

        return array_keys($this->added);
    }

    /** @return array<string, array<string, mixed>> */
    private function idFieldFor(BookMedia $media): array
    {
        $this->build();
        $fields = [];

        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $formType = null, array $options = []) use (&$form, &$fields) {
            $fields[$name] = $options;

            return $form;
        });

        foreach ($this->listeners as $listener) {
            $listener(new PreSetDataEvent($form, $media));
        }

        return $fields;
    }
}

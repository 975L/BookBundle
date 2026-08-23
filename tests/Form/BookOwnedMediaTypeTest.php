<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookMarketing;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Form\BookMarketingType;
use c975L\BookBundle\Form\BookOwnedMediaType;
use c975L\BookBundle\Form\BookPresseType;
use c975L\BookBundle\Form\BookVideoType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The three collections of files a book holds are the same form: what each declares of its own is the entity behind its rows, the word naming its file and whether an address can stand in for it
class BookOwnedMediaTypeTest extends TestCase
{
    /** @var array<string, array{type: ?string, options: array<string, mixed>}> */
    private array $added = [];

    /** @return list<string> */
    private function build(BookOwnedMediaType $type): array
    {
        $this->added = [];

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $formType = null, array $options = []) use (&$builder) {
            $this->added[$name] = ['type' => $formType, 'options' => $options];

            return $builder;
        });
        $builder->method('addEventListener')->willReturnCallback(static fn (): FormBuilderInterface => $builder);

        $type->buildForm($builder, []);

        return array_keys($this->added);
    }

    /** @return array<string, mixed> */
    private function options(BookOwnedMediaType $type): array
    {
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        return $resolver->resolve([]);
    }

    // A filmed review is pasted like a video of the book: both collections therefore ask for the address, and in the same place - between the title and the file
    public function testAVideoAndAPresseRowBothAskForAHostedAddress(): void
    {
        $this->assertSame(['position', 'title', 'youtubeUrl', 'noCookie', 'file'], $this->build(new BookVideoType()));
        $this->assertSame(['position', 'title', 'youtubeUrl', 'noCookie', 'file'], $this->build(new BookPresseType()));
    }

    // A promotional visual is always an uploaded file: offering it an address field would offer a player nothing prints
    public function testAMarketingRowIsOnlyEverAFile(): void
    {
        $this->assertSame(['position', 'title', 'file'], $this->build(new BookMarketingType()));
    }

    // The file's label is the only word each of the three lays, the collection being named by the group it is written in
    public function testEachCollectionNamesItsOwnFile(): void
    {
        $this->build(new BookVideoType());
        $this->assertSame('label.video', $this->added['file']['options']['label']);

        $this->build(new BookPresseType());
        $this->assertSame('label.presse', $this->added['file']['options']['label']);

        $this->build(new BookMarketingType());
        $this->assertSame('label.marketing', $this->added['file']['options']['label']);
    }

    // All three write in the book's words, each on its own entity
    public function testEachCollectionResolvesToItsOwnEntity(): void
    {
        $this->assertSame(BookVideo::class, $this->options(new BookVideoType())['data_class']);
        $this->assertSame(BookPresse::class, $this->options(new BookPresseType())['data_class']);
        $this->assertSame(BookMarketing::class, $this->options(new BookMarketingType())['data_class']);
        $this->assertSame('book', $this->options(new BookMarketingType())['translation_domain']);
    }
}

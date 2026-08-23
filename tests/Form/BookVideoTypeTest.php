<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Form\BookVideoType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

// A video of a book is hosted as readily as it is uploaded, exactly as a presse row is: Book:Video prints the player of a row naming an address, so the form has to let that address be pasted - whatever its form, the rewriting box being ticked in advance (see BookVideoNoCookieListener)
class BookVideoTypeTest extends TestCase
{
    /** @var array<string, array{type: ?string, options: array<string, mixed>}> */
    private array $added = [];

    private function build(): void
    {
        $this->added = [];

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$builder) {
            $this->added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });
        $builder->method('addEventListener')->willReturnCallback(static fn (): FormBuilderInterface => $builder);

        new BookVideoType()->buildForm($builder, []);
    }

    public function testBuildFormAsksForWhatOneVideoHolds(): void
    {
        $this->build();

        $this->assertSame(['position', 'title', 'youtubeUrl', 'noCookie', 'file'], array_keys($this->added));
        $this->assertSame(HiddenType::class, $this->added['position']['type']);
        $this->assertSame(TextType::class, $this->added['title']['type']);
        $this->assertSame(UrlType::class, $this->added['youtubeUrl']['type']);
        $this->assertSame(CheckboxType::class, $this->added['noCookie']['type']);
        $this->assertSame('label.video', $this->added['file']['options']['label']);
    }

    // Neither is asked for: a row is one or the other, and a video already uploaded has no address to give
    public function testNeitherTheAddressNorTheFileIsRequired(): void
    {
        $this->build();

        $this->assertFalse($this->added['title']['options']['required']);
        $this->assertFalse($this->added['youtubeUrl']['options']['required']);
    }

    // The words are UiBundle's, which already translated them for its own video blocks: the field says its domain, the rest of the form speaking the book's
    public function testTheAddressAndItsCheckboxBorrowUiBundlesOwnWords(): void
    {
        $this->build();

        $this->assertSame(['label.video_url', 'ui'], [$this->added['youtubeUrl']['options']['label'], $this->added['youtubeUrl']['options']['translation_domain']]);
        $this->assertSame(['label.video_no_cookie', 'ui'], [$this->added['noCookie']['options']['label'], $this->added['noCookie']['options']['translation_domain']]);
    }
}

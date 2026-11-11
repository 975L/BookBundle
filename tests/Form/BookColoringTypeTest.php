<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Entity\BookColoring;
use c975L\BookBundle\Form\BookColoringType;
use c975L\BookBundle\Service\BookColoringPalette;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// A drawing asks for its day and the illustration its crayons are read off, read on the save it is sent with and only when it gives some; a language screen asks for the title alone
class BookColoringTypeTest extends TestCase
{
    /** @var list<string> */
    private array $added = [];

    /** @var list<callable> */
    private array $postSubmit = [];

    /** @param array<string, mixed> $options */
    private function build(array $options = []): void
    {
        $this->added = [];
        $this->postSubmit = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name) use ($builder): FormBuilderInterface {
            $this->added[] = $name;

            return $builder;
        });
        $builder->method('addEventListener')->willReturnCallback(function (string $event, callable $listener) use ($builder): FormBuilderInterface {
            if (FormEvents::POST_SUBMIT === $event) {
                $this->postSubmit[] = $listener;
            }

            return $builder;
        });

        new BookColoringType(new BookColoringPalette())->buildForm($builder, $options);
    }

    public function testItAsksForTheDayAndTheIllustration(): void
    {
        $this->build();

        $this->assertSame(['position', 'title', 'file', 'publishedOn', 'source'], $this->added);
    }

    public function testALanguageScreenAsksForTheTitleAlone(): void
    {
        $this->build(['translation_locale' => 'en']);

        $this->assertNotContains('publishedOn', $this->added);
        $this->assertNotContains('source', $this->added);
    }

    public function testAnIllustrationGivesTheCrayons(): void
    {
        $coloring = $this->submit(true);

        $this->assertSame(BookColoringPalette::FIXED, \array_slice($coloring->getPalette(), -3));
        $this->assertGreaterThan(3, \count($coloring->getPalette()));
    }

    // A file that is no image leaves the crayons as they were
    public function testAnUnreadableIllustrationLeavesTheCrayons(): void
    {
        $this->assertSame(['#123456'], $this->submit(false)->getPalette());
    }

    private function submit(bool $readable): BookColoring
    {
        $path = tempnam(sys_get_temp_dir(), 'source');
        if ($readable) {
            $image = imagecreatetruecolor(20, 20);
            imagefilledrectangle($image, 0, 0, 9, 19, (int) imagecolorallocate($image, 200, 30, 30));
            imagefilledrectangle($image, 10, 0, 19, 19, (int) imagecolorallocate($image, 30, 30, 200));
            imagepng($image, $path);
        } else {
            file_put_contents($path, 'not an image');
        }

        $source = $this->createStub(FormInterface::class);
        $source->method('getData')->willReturn(new UploadedFile($path, 'source.png', 'image/png', null, true));
        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturn($source);

        $coloring = new BookColoring()->setPalette(['#123456']);
        $this->build();
        foreach ($this->postSubmit as $listener) {
            $listener(new PostSubmitEvent($form, $coloring));
        }
        unlink($path);

        return $coloring;
    }
}

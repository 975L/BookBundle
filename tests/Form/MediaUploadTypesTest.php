<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Form;

use c975L\BookBundle\Form\BookCoverType;
use c975L\BookBundle\Form\BookFlipbookType;
use c975L\BookBundle\Form\BookMarketingType;
use c975L\BookBundle\Form\CharacterMediaType;
use c975L\BookBundle\Form\ContributorMediaType;
use c975L\BookBundle\Form\SerieMediaType;
use c975L\BookBundle\Form\StripMediaType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validation;

// What each upload field refuses, judged by the real validator on the bytes of real files: the size alone used to be checked, whatever the file
class MediaUploadTypesTest extends TestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private ?string $path = null;

    protected function tearDown(): void
    {
        if (null !== $this->path) {
            unlink($this->path);
        }
    }

    /** @return iterable<string, array{AbstractType}> */
    public static function imageTypes(): iterable
    {
        yield 'cover' => [new BookCoverType()];
        yield 'serie' => [new SerieMediaType()];
        yield 'strip' => [new StripMediaType()];
        yield 'contributor' => [new ContributorMediaType()];
        yield 'character' => [new CharacterMediaType()];
    }

    // These rows are only ever images: a script, a page, an SVG or a PDF stays out
    #[DataProvider('imageTypes')]
    public function testAnImageFieldTakesAnImageAndNothingElse(AbstractType $type): void
    {
        $this->assertSame(0, $this->violations($type, 'image.png', (string) base64_decode(self::PNG, true)));

        foreach ($this->scripts() + ['file.pdf' => "%PDF-1.4\n"] as $name => $content) {
            $this->assertSame(1, $this->violations($type, $name, $content), $name);
        }
    }

    // A row holding anything takes a document or the timecodes too, and a script no more than an image field does
    public function testAFileFieldTakesWhatABookHoldsAndRefusesAScript(): void
    {
        $type = new BookMarketingType();

        foreach (['image.png' => (string) base64_decode(self::PNG, true), 'IMAGE.JPG' => (string) base64_decode(self::PNG, true), 'file.pdf' => "%PDF-1.4\n", 'cues.vtt' => "WEBVTT\n\n1\n00:00.000 --> 00:01.000\nx\n"] as $name => $content) {
            $this->assertSame(0, $this->violations($type, $name, $content), $name);
        }

        foreach ($this->scripts() as $name => $content) {
            $this->assertSame(1, $this->violations($type, $name, $content), $name);
        }
    }

    // Bytes a detection reads as a video or a PDF, under a name a browser opens as an SVG: UiMediaNamer would keep that name
    public function testAFileFieldRefusesAnSvgReadAsSomethingElse(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>';

        foreach (['<!--ftypmp42 -->' . $svg, '<!--%PDF-1.4-->' . $svg] as $content) {
            $this->assertSame(1, $this->violations(new BookMarketingType(), 'video.svg', $content), $content);
        }
    }

    // The flipbook is an mp4 by its name as much as by its bytes, the same SVG staying out of it
    public function testAFlipbookRefusesAnSvgReadAsAVideo(): void
    {
        $content = '<!--ftypmp42 --><svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>';

        $this->assertSame(0, $this->violations(new BookFlipbookType(), 'flipbook.mp4', $content));
        $this->assertSame(1, $this->violations(new BookFlipbookType(), 'flipbook.svg', $content));
    }

    /** @return array<string, string> */
    private function scripts(): array
    {
        return [
            'script.php' => '<?php echo 1;',
            'page.html' => '<html><script>alert(1)</script></html>',
            'picture.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            // Detected as text/plain, the reason text/plain stays out of the lists: the stored name would keep its .svg
            'comment.svg' => '<!-- c --><svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
        ];
    }

    private function violations(AbstractType $type, string $name, string $content): int
    {
        $this->path ??= (string) tempnam(sys_get_temp_dir(), 'book');
        file_put_contents($this->path, $content);

        return Validation::createValidator()->validate(new UploadedFile($this->path, $name, null, null, true), $this->constraints($type))->count();
    }

    /** @return list<Constraint> */
    private function constraints(AbstractType $type): array
    {
        $constraints = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $formType = null, array $options = []) use (&$builder, &$constraints) {
            $constraints = 'file' === $name ? $options['constraints'] : $constraints;

            return $builder;
        });
        $builder->method('addEventListener')->willReturnSelf();

        $type->buildForm($builder, ['kind_editable' => false, 'translation_locale' => null]);

        return $constraints;
    }
}

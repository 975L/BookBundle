<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Twig;

use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Twig\BookHighresExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;

// The zoom is only offered for a planche whose derivative was really produced: the name can be spelled for every media, the file only exists for those uploaded since this bundle started asking for it
class BookHighresExtensionTest extends TestCase
{
    private string $projectDir = '';

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/book-highres-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public/medias/book/strips');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    // The suffix goes before the extension, where the listener saved the file
    public function testItNamesTheFileTheListenerSavedBesideTheStoredOne(): void
    {
        $this->write('medias/book/strips/page-la-tribu-ecole-abc123-highres.webp');

        $this->assertSame(
            'medias/book/strips/page-la-tribu-ecole-abc123-highres.webp',
            $this->extension()->stripHighres($this->media('medias/book/strips/page-la-tribu-ecole-abc123.webp'))
        );
    }

    // A planche uploaded before the derivatives were asked for: its name can be spelled all the same, and offering the zoom on it would hand the dialog an address answering 404
    public function testItAnswersNothingForAMediaWhoseDerivativeWasNeverProduced(): void
    {
        $this->assertNull(
            $this->extension()->stripHighres($this->media('medias/book/strips/page-la-tribu-ecole-abc123.webp'))
        );
    }

    // A media row exists from the moment its planche is saved, whether or not a file has been picked
    public function testItAnswersNothingForAMediaCarryingNoFileAtAll(): void
    {
        $this->assertNull($this->extension()->stripHighres(new StripMedia()));
        $this->assertNull($this->extension()->stripHighres(null));
    }

    private function extension(): BookHighresExtension
    {
        return new BookHighresExtension(new ParameterBag(['kernel.project_dir' => $this->projectDir]));
    }

    private function media(string $name): StripMedia
    {
        return new StripMedia()->setName($name);
    }

    private function write(string $path): void
    {
        new Filesystem()->dumpFile($this->projectDir . '/public/' . $path, 'x');
    }
}

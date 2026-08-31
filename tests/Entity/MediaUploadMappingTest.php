<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\BookMarketing;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookPresse;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\ContributorMedia;
use c975L\BookBundle\Entity\Media;
use c975L\BookBundle\Entity\SerieMedia;
use c975L\BookBundle\Entity\StripMedia;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Metadata\Driver\AttributeDriver;
use Vich\UploaderBundle\Metadata\Driver\AttributeReader;
use Vich\UploaderBundle\Metadata\MetadataReader;

// The upload is declared once, on Media::$file, where Vich reads it off the parent class - what each subclass keeps is the #[Vich\Uploadable] marking it uploadable, an attribute PHP does not inherit. Both halves are checked here: an upgrade of the bundle dropping the parent walk would otherwise take the seven uploads down without a single test turning red
class MediaUploadMappingTest extends TestCase
{
    /** @return list<array{class-string<Media>}> */
    public static function subclasses(): array
    {
        return [
            [BookMarketing::class],
            [BookMedia::class],
            [BookPresse::class],
            [BookVideo::class],
            [ContributorMedia::class],
            [SerieMedia::class],
            [StripMedia::class],
        ];
    }

    /**
     * @param class-string<Media> $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('subclasses')]
    public function testEverySubclassUploadsThroughTheFieldDeclaredOnMedia(string $class): void
    {
        $reader = new MetadataReader(new MetadataFactory(new AttributeDriver(new AttributeReader(), [])));

        $this->assertTrue($reader->isUploadable($class, 'block_media'));

        $fields = $reader->getUploadableFields($class);

        $this->assertArrayHasKey('file', $fields);
        $this->assertSame('block_media', $fields['file']['mapping']);
        $this->assertSame('name', $fields['file']['fileNameProperty']);
        $this->assertSame('size', $fields['file']['size']);
    }
}

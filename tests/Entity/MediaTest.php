<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Book;
use c975L\BookBundle\Entity\BookMedia;
use c975L\BookBundle\Entity\BookVideo;
use c975L\BookBundle\Entity\Contributor;
use c975L\BookBundle\Entity\Serie;
use c975L\BookBundle\Entity\Strip;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// UiBundle's Slider reads every media through the accessors its own entity carries - a media of this hierarchy answering none of them takes the page down, so the whole contract is checked here rather than the fields one by one
class MediaTest extends TestCase
{
    public function testAMediaReadsItsMimeTypeOffItsName(): void
    {
        $this->assertSame('image/webp', new BookMedia()->setName('medias/book/cover.webp')->getMimeType());
        $this->assertSame('video/mp4', new BookMedia()->setName('medias/book/trailer.MP4')->getMimeType());
        $this->assertSame('application/epub+zip', new BookMedia()->setName('medias/book/book.epub')->getMimeType());
    }

    // A recording can also arrive as ogg: without it the "Listen" card did not see the file, its type falling back on a binary one
    public function testARecordingIsRecognizedWhateverTheAudioExtension(): void
    {
        $this->assertSame('audio/mpeg', new BookMedia()->setName('medias/book/chapter-01.mp3')->getMimeType());
        $this->assertSame('audio/ogg', new BookMedia()->setName('medias/book/chapter-01.ogg')->getMimeType());
        $this->assertSame('audio/ogg', new BookMedia()->setName('medias/book/chapter-01.OGA')->getMimeType());
    }

    // Ticked from the start: the privacy-respecting address is the only origin the site's security policy allows
    public function testAMediaAsksForThePrivacyRespectingAddressUntilToldOtherwise(): void
    {
        $this->assertTrue(new BookMedia()->isNoCookie());
        $this->assertFalse(new BookMedia()->setNoCookie(false)->isNoCookie());
    }

    // Whatever the Slider is handed must still be told apart from a video, so an unnamed or unknown file answers rather than breaking
    public function testAnUnknownExtensionFallsBackOnABinaryMimeType(): void
    {
        $this->assertSame('application/octet-stream', new BookMedia()->setName('medias/book/archive.xyz')->getMimeType());
        $this->assertSame('application/octet-stream', new BookMedia()->getMimeType());
    }

    public function testTheTitleStandsAsTheAlternativeText(): void
    {
        $this->assertSame('Couverture', new BookMedia()->setTitle('Couverture')->getAlt());
    }

    public function testAMediaAnswersEveryAccessorTheSliderReads(): void
    {
        $media = new BookMedia()->setName('medias/book/page-01.webp')->setTitle('Page 1');

        $this->assertNull($media->getLabel());
        $this->assertNull($media->getWidth());
        $this->assertNull($media->getHeight());
        $this->assertNull($media->getCredits());
        $this->assertSame([], $media->getCssClasses());
        $this->assertFalse($media->isAbove());
        $this->assertFalse($media->isRightsReserved());
    }

    // A cover added in the back-office and saved before any file is picked used to reach the database with no date at all, which the column refuses - it now carries one from the moment it is built
    public function testANewMediaAlreadyCarriesADate(): void
    {
        $this->assertNotNull(new BookMedia()->getUpdatedAt());
    }

    // Carrying a date is what makes an empty row writable, so nothing but the validation stops one: a template asking for the first media of a collection is handed that empty row instead of falling back on its placeholder
    public function testARowWithNoFileAtAllIsRefused(): void
    {
        $violations = $this->validator()->validate(new BookMedia());

        $this->assertCount(1, $violations);
        $this->assertSame('file', $violations[0]->getPropertyPath());
        $this->assertSame('label.media_without_file', $violations[0]->getMessageTemplate());
    }

    // Vich names the file after the validation has run, so a row holding the upload alone is the normal shape of a new entry
    public function testARowHoldingItsUploadOrItsNameIsAccepted(): void
    {
        $this->assertCount(0, $this->validator()->validate(new BookMedia()->setName('medias/book/cover.webp')));
    }

    // A hosted video stands for its own file, its address being what the player reads
    public function testAHostedVideoStandsForItsFile(): void
    {
        $this->assertCount(0, $this->validator()->validate(new BookVideo()->setYoutubeUrl('https://www.youtube.com/watch?v=abc')));
    }

    // The constraint above only reaches the form through the collections carrying the rows: without Valid on each, an empty entry is validated by nobody. Read off the attributes rather than by validating an owner, whose UniqueEntity asks for the Doctrine services a unit test has none of
    public function testEveryCollectionOfMediasIsCascadedInto(): void
    {
        $owners = [
            Book::class => ['medias', 'videos', 'presses', 'marketings'],
            Serie::class => ['medias'],
            Strip::class => ['medias'],
            Contributor::class => ['medias'],
        ];

        foreach ($owners as $owner => $properties) {
            foreach ($properties as $property) {
                $this->assertNotEmpty(
                    new \ReflectionProperty($owner, $property)->getAttributes(Assert\Valid::class),
                    sprintf('"%s::$%s" holds medias without being cascaded into', $owner, $property)
                );
            }
        }
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}

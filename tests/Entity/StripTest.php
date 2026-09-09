<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Entity;

use c975L\BookBundle\Entity\Character;
use c975L\BookBundle\Entity\Strip;
use c975L\BookBundle\Entity\StripMedia;
use c975L\BookBundle\Enum\StripMediaKind;
use PHPUnit\Framework\TestCase;

class StripTest extends TestCase
{
    // The shape the row of chips and the structured data read, now built from the relation rather than from a comma-separated field
    public function testCharactersListPairsTheNameWithItsSlug(): void
    {
        $strip = new Strip();

        foreach ([['Timothée', 'timothee'], ['Contes du Soir', 'contes-du-soir'], ['Zoé', 'zoe']] as [$name, $slug]) {
            $strip->addCharacter(new Character()->setName($name)->setSlug($slug));
        }

        $this->assertSame(
            [
                ['name' => 'Timothée', 'slug' => 'timothee'],
                ['name' => 'Contes du Soir', 'slug' => 'contes-du-soir'],
                ['name' => 'Zoé', 'slug' => 'zoe'],
            ],
            $strip->getCharactersList()
        );
    }

    // A planche naming nobody says so, rather than answering a list holding an empty pair
    public function testAPlancheNamingNobodyListsNoCharacter(): void
    {
        $this->assertSame([], new Strip()->getCharactersList());
    }

    // The same character named twice is held once: a chip drawn twice would read as two people
    public function testTheSameCharacterIsHeldOnce(): void
    {
        $strip = new Strip();
        $zoe = new Character()->setName('Zoé')->setSlug('zoe');
        $strip->addCharacter($zoe)->addCharacter($zoe);

        $this->assertCount(1, $strip->getCharacters());

        $strip->removeCharacter($zoe);
        $this->assertCount(0, $strip->getCharacters());
    }

    public function testMediasAreReadByTheRoleTheyCarry(): void
    {
        $strip = new Strip();
        $page = new StripMedia()->setKind(StripMediaKind::Page->value);
        $first = new StripMedia()->setKind(StripMediaKind::Panel->value);
        $second = new StripMedia()->setKind(StripMediaKind::Panel->value);
        $strip->addMedia($page)->addMedia($first)->addMedia($second);

        $this->assertSame([$page], array_values($strip->getMediasByKind('page')->toArray()));
        $this->assertSame([$first, $second], array_values($strip->getMediasByKind('case')->toArray()));
        $this->assertTrue($strip->getMediasByKind('card')->isEmpty());
    }

    // A planche imported before the roles existed carries none, and the page falls back on the single gallery rather than showing nothing - which is what keeps a site whose planches are one picture untouched
    public function testAMediaWithNoRoleAnswersToNoRole(): void
    {
        $strip = new Strip();
        $legacy = new StripMedia();
        $strip->addMedia($legacy);

        $this->assertTrue($strip->getMediasByKind('page')->isEmpty());
        $this->assertTrue($strip->getMediasByKind('case')->isEmpty());
        $this->assertCount(1, $strip->getMedias());
    }

    // What the back-office binds a field to, one collection per role: an editor picks the field, the adder writes the role (see Book::getBuyLinks() for the same shape on a book's links)
    public function testEachRoleIsBoundAsACollectionOfItsOwn(): void
    {
        $strip = new Strip();
        $page = new StripMedia();
        $case = new StripMedia();
        $strip->addPageMedia($page)->addCaseMedia($case);

        $this->assertSame(StripMediaKind::Page->value, $page->getKind());
        $this->assertSame(StripMediaKind::Panel->value, $case->getKind());
        $this->assertSame([$page], array_values($strip->getPageMedias()->toArray()));
        $this->assertSame([$case], array_values($strip->getCaseMedias()->toArray()));
        $this->assertCount(2, $strip->getMedias());
    }

    // Whatever no field of its own shows would otherwise be editable from nowhere: a planche imported before the roles existed, but also the card and the thumbnail, which are roles this bundle writes and the back-office does not split out
    public function testEveryMediaNoFieldOfItsOwnShowsIsGatheredTogether(): void
    {
        $strip = new Strip();
        $legacy = new StripMedia();
        $unknown = new StripMedia()->setKind('whatever');
        $card = new StripMedia()->setKind(StripMediaKind::Card->value);
        $thumbnail = new StripMedia()->setKind(StripMediaKind::Thumbnail->value);
        $page = new StripMedia()->setKind(StripMediaKind::Page->value);
        $case = new StripMedia()->setKind(StripMediaKind::Panel->value);
        $strip->addMedia($legacy)->addMedia($unknown)->addMedia($card)->addMedia($thumbnail)->addMedia($page)->addMedia($case);

        $this->assertSame([$legacy, $unknown, $card, $thumbnail], array_values($strip->getOtherMedias()->toArray()));
    }

    // No media may fall between the three collections: what the two roles do not claim is what the third holds, and the sum is the planche's whole collection
    public function testTheThreeCollectionsHoldEveryMediaBetweenThem(): void
    {
        $strip = new Strip();

        foreach ([null, 'whatever', 'card', 'thumbnail', 'page', 'case'] as $kind) {
            $strip->addMedia(new StripMedia()->setKind($kind));
        }

        $held = $strip->getPageMedias()->count()
            + $strip->getCaseMedias()->count()
            + $strip->getOtherMedias()->count();

        $this->assertSame($strip->getMedias()->count(), $held);
    }

    // The picture a listing stands the planche on, read by role: since the roles the first media of the collection may be a panel, which is not what a wall of thumbnails is meant to show
    public function testTheThumbnailMediaIsReadByRoleAndNotByRank(): void
    {
        $strip = new Strip();
        $case = new StripMedia()->setKind(StripMediaKind::Panel->value);
        $thumbnail = new StripMedia()->setKind(StripMediaKind::Thumbnail->value);
        $card = new StripMedia()->setKind(StripMediaKind::Card->value);
        $strip->addMedia($case)->addMedia($card)->addMedia($thumbnail);

        $this->assertSame($thumbnail, $strip->getThumbnailMedia());
    }

    // The card standing in where no thumbnail was uploaded, and the first media where neither role was told apart - a planche of one picture keeps showing it
    public function testTheThumbnailMediaFallsBackOnTheCardThenOnTheFirstOne(): void
    {
        $withCard = new Strip();
        $case = new StripMedia()->setKind(StripMediaKind::Panel->value);
        $card = new StripMedia()->setKind(StripMediaKind::Card->value);
        $withCard->addMedia($case)->addMedia($card);

        $this->assertSame($card, $withCard->getThumbnailMedia());

        $bare = new Strip();
        $only = new StripMedia();
        $bare->addMedia($only);

        $this->assertSame($only, $bare->getThumbnailMedia());
        $this->assertNull(new Strip()->getThumbnailMedia());
    }

    // Every view is a window on the one mapped collection: removing through any of them takes the media off the planche
    public function testRemovingThroughARoleTakesTheMediaOffThePlanche(): void
    {
        $strip = new Strip();
        $page = new StripMedia();
        $strip->addPageMedia($page);
        $strip->removePageMedia($page);

        $this->assertCount(0, $strip->getMedias());
        $this->assertNull($page->getStrip());
    }
}

<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\Group;

// assets/js/reader.js run over an album and its recording, the two driving each other - the interesting half being the loop it must not close: turning a page moves the playhead, the playhead raises "timeupdate", and "timeupdate" turns the page, the guard being a flag released on the next frame that only a scenario can put under load
#[Group('browser')]
class ReaderBehaviourTest extends JsCase
{
    // Deliberately out of order, the pages being entered in whatever order they were written
    private const array CUES = [
        ['page' => 3, 'start' => 20],
        ['page' => 1, 'start' => 0],
        ['page' => 2, 'start' => 10],
    ];

    public function testTheVoiceTurnsThePageWhenItReachesItsCue(): void
    {
        $turned = $this->reader('at(12); return clicked;');

        $this->assertSame([1], $turned, 'The recording passed a page cue and the album stayed on the page before it.');
    }

    // currentCue() reads the last cue already passed, which is only right on a sorted list
    public function testThePagesAreFollowedInTimeRatherThanInTheOrderTheyWereEntered(): void
    {
        $this->assertSame([2], $this->reader('at(25); return clicked;'), 'The recording reached the last cue and the album turned to some other page.');
    }

    // An album whose page carries no timing yet: the field is empty, and a cue at NaN is passed by everything
    public function testAPageWithNoTimingIsNotAPageTheVoiceCanTurnTo(): void
    {
        $this->assertSame(
            [],
            $this->reader('at(12); return clicked;', [['page' => 2, 'start' => null], ['page' => 3, 'start' => 'bientot']]),
            'A page whose timing was never filled in is turned to as though its cue were at the very beginning.'
        );
    }

    // The other way round: the reader turns a page by hand and the voice follows rather than going on reading the page that is no longer shown
    public function testTurningAPageByHandMovesTheRecordingToThatPagesCue(): void
    {
        $this->assertSame(10, $this->reader('dot(2).click(); return audio().currentTime;'), 'Turning to a page left the recording where it was, reading a page nobody is looking at.');
    }

    // The round trip: the seek raises a "timeupdate" of its own, and honouring it would turn the page that has just moved the playhead
    public function testTheSeekDoesNotTurnThePageItWasAskedFor(): void
    {
        $held = $this->reader(
            'audio().currentTime = 0;
             dot(3).click();
             const byHand = clicked.length;
             // Where the player still reports itself while the seek is on its way, which is exactly when it raises the event
             audio().currentTime = 0;
             tick();
             const during = clicked.length - byHand;
             audio().currentTime = 20;
             await frame();
             at(0);

             return { during, after: clicked.length - byHand };'
        );

        $this->assertSame(0, $held['during'], 'The recording turned a page while the seek was still on its way, sending the reader straight back to the page they had just left.');
        $this->assertSame(1, $held['after'], 'The guard was never released, so the voice stops turning pages for the rest of the recording.');
    }

    // A dot pointing at a page the recording has no cue for leaves the playhead where it was rather than seeking to nothing
    public function testTurningToAPageTheRecordingNeverReachesLeavesItAlone(): void
    {
        $this->assertSame(
            5,
            $this->reader('audio().currentTime = 5; dot(4).click(); return audio().currentTime;', [['page' => 1, 'start' => 0]]),
            'Turning to a page with no cue moved the recording anyway.'
        );
    }

    // The album is read on its own, the recording being what an album may not have
    public function testAnAlbumWithNoRecordingIsStillTurnedByHand(): void
    {
        $this->assertTrue(
            (bool) $this->reader('dot(2).click(); return true;', null, '<div data-controller="reader" data-reader-cues-value="[]" data-reader-auto-advance-value="true"><div class="slider-dot" data-slide="1"></div></div>'),
            'An album with no recording takes its own page down.'
        );
    }

    // The setting is the reader's: an album read at their own pace must not have its pages turned under them
    public function testAnAlbumThatDoesNotAdvanceOnItsOwnStillFollowsTheHand(): void
    {
        $manual = $this->reader('at(25); const turned = clicked.length; dot(2).click(); return { turned, sought: audio().currentTime };', null, null, false);

        $this->assertSame(0, $manual['turned'], 'The recording turned a page on an album asked to be read at the reader\'s own pace.');
        $this->assertSame(10, $manual['sought'], 'Turning a page by hand no longer moves the recording once the album stops advancing on its own.');
    }

    // Turbo caches the page as it stands, and a recording left listened to turns the pages of an album nobody is reading
    public function testNeitherListenerOutlivesTheAlbum(): void
    {
        $left = $this->reader(
            'const player = audio();
             const first = dot(1);
             document.createElement("div").appendChild(root.querySelector("[data-controller]"));
             await new Promise((r) => setTimeout(r, 0));
             player.currentTime = 25;
             player.dispatchEvent(new Event("timeupdate"));
             const turned = clicked.length;
             first.click();

             return { turned, sought: player.currentTime };'
        );

        $this->assertSame(0, $left['turned'], 'The recording goes on turning the pages of an album that is no longer on the page.');
        $this->assertSame(25, $left['sought'], 'A dot goes on moving a recording nobody is listening to.');
    }

    private function reader(string $probe, ?array $cues = null, ?string $html = null, bool $autoAdvance = true): mixed
    {
        $preamble = 'const audio = () => root.querySelector("audio");
             const dot = (page) => root.querySelector("[data-slide=\"" + (page - 1) + "\"]");
             const clicked = [];
             root.querySelectorAll(".slider-dot").forEach((el) => { el.addEventListener("click", () => clicked.push(Number(el.dataset.slide))); });
             const tick = () => audio().dispatchEvent(new Event("timeupdate"));
             const frame = () => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
             const at = (seconds) => { audio().currentTime = seconds; tick(); }; ';

        return $this->observe($html ?? $this->page($cues ?? self::CUES, $autoAdvance), ['reader' => 'reader'], $preamble . $probe);
    }

    // The album as the reader template draws it: the recording, and the slider dots UiBundle owns - driven through the dots rather than through its controller, so the two stay independent
    private function page(array $cues, bool $autoAdvance): string
    {
        return sprintf(
            '<div data-controller="reader" data-reader-cues-value="%s" data-reader-auto-advance-value="%s">
                <audio></audio>
                <div class="slider-dots">
                    <button type="button" class="slider-dot" data-slide="0"></button>
                    <button type="button" class="slider-dot" data-slide="1"></button>
                    <button type="button" class="slider-dot" data-slide="2"></button>
                    <button type="button" class="slider-dot" data-slide="3"></button>
                </div>
            </div>',
            htmlspecialchars(json_encode($cues, \JSON_THROW_ON_ERROR), \ENT_QUOTES),
            $autoAdvance ? 'true' : 'false'
        );
    }
}

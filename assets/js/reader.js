/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Reads an illustrated album page by page along its recording: the voice turns the pages, and turning one by hand moves the playhead to that page's cue.
// Drives UiBundle's slider through its own dots rather than its controller's methods - the dots are the slider's public surface, so the two stay independent.
export default class extends Controller {
    static values = { cues: Array, autoAdvance: Boolean };

    connect() {
        this.audio = this.element.querySelector("audio");
        this.dots = Array.from(this.element.querySelectorAll(".slider-dot"));
        // Sorted here rather than trusting the order the pages were entered in: currentCue() reads the last cue already passed
        this.cues = this.cuesValue.filter((cue) => Number.isFinite(cue.start)).sort((a, b) => a.start - b.start);
        this.page = 1;

        if (!this.audio || 0 === this.dots.length) {
            return;
        }

        // Guards the round trip: seeking raises "timeupdate", which would turn the page that just moved the playhead
        this.seeking = false;

        this.onTimeUpdate = () => this.followVoice();
        this.onDotClick = (event) => this.followReader(event);

        if (this.autoAdvanceValue && this.cues.length > 0) {
            this.audio.addEventListener("timeupdate", this.onTimeUpdate);
        }
        this.dots.forEach((dot) => { dot.addEventListener("click", this.onDotClick); });
    }

    disconnect() {
        this.audio?.removeEventListener("timeupdate", this.onTimeUpdate);
        this.dots?.forEach((dot) => { dot.removeEventListener("click", this.onDotClick); });
    }

    // The page the recording has reached - the last cue it has passed
    followVoice() {
        if (this.seeking) {
            return;
        }

        let page = this.page;
        for (const cue of this.cues) {
            if (cue.start <= this.audio.currentTime) {
                page = cue.page;
            }
        }

        if (page !== this.page) {
            this.page = page;
            this.dots[page - 1]?.click();
        }
    }

    // A page turned by hand moves the recording to that page's cue, so the voice never reads a page that is no longer shown
    followReader(event) {
        const page = Number.parseInt(event.currentTarget.dataset.slide ?? "", 10) + 1;
        const cue = this.cues.find((entry) => entry.page === page);
        if (Number.isNaN(page) || undefined === cue) {
            return;
        }

        this.page = page;
        this.seeking = true;
        this.audio.currentTime = cue.start;
        // Released on the next frame: the seek's own "timeupdate" fires before it
        requestAnimationFrame(() => {
            this.seeking = false;
        });
    }
}

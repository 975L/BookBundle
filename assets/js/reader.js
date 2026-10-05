/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Reads an illustrated album page by page along its recording: the voice turns the pages, and turning one by hand moves the playhead to that page's cue.
// Talks to UiBundle's slider only through its public surface - its dots to turn a page, its "slider:changed" event to learn one was turned - so the two stay independent.
export default class extends Controller {
    static values = { cues: Array, cuesUrl: String, autoAdvance: Boolean };

    async connect() {
        this.audio = this.element.querySelector("audio");
        this.dots = Array.from(this.element.querySelectorAll(".slider-dot"));
        this.page = 1;

        if (!this.audio || 0 === this.dots.length) {
            return;
        }

        // A WebVTT file wins over the inline cues; one that cannot be read leaves the pages to the reader alone
        const cues = this.cuesUrlValue ? await this.readCuesFile(this.cuesUrlValue) : this.cuesValue;
        // Turbo may have taken the page away while the file was on its way: nothing left to listen to
        if (!this.element.isConnected) {
            return;
        }
        // Sorted here rather than trusting the order the pages were entered in: followVoice() reads the last cue already passed
        this.cues = cues.filter((cue) => Number.isFinite(cue.start)).sort((a, b) => a.start - b.start);

        // Guards the round trip: seeking raises "timeupdate", which would turn the page that just moved the playhead
        this.seeking = false;

        this.onTimeUpdate = () => this.followVoice();
        this.onPageTurned = (event) => this.followReader(event);

        if (this.autoAdvanceValue && this.cues.length > 0) {
            this.audio.addEventListener("timeupdate", this.onTimeUpdate);
        }
        // The event rather than the dots' clicks: the arrows, a tap on the page and a swipe turn it too
        this.element.addEventListener("slider:changed", this.onPageTurned);
    }

    disconnect() {
        this.audio?.removeEventListener("timeupdate", this.onTimeUpdate);
        this.element.removeEventListener("slider:changed", this.onPageTurned);
    }

    // The cues of a WebVTT file: each cue's identifier is its page's number, its start time where that page begins
    async readCuesFile(url) {
        try {
            const response = await fetch(url);
            if (!response.ok) {
                return [];
            }
            const blocks = (await response.text()).replace(/\r/g, "").split(/\n{2,}/);

            return blocks.flatMap((block) => {
                const match = block.trim().match(/^(\d+)\n(?:(\d+):)?(\d{2}):(\d{2}(?:\.\d+)?)\s*-->/);
                if (!match) {
                    return [];
                }
                const [, page, hours, minutes, seconds] = match;

                return [{ page: Number(page), start: Number(hours ?? 0) * 3600 + Number(minutes) * 60 + Number(seconds) }];
            });
        } catch {
            return [];
        }
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

    // A page turned by hand moves the recording to that page's cue, so the voice never reads a page that is no longer shown. The page the voice has just turned comes back here too, already current: nothing to seek
    followReader(event) {
        const page = Number(event.detail?.page);
        const cue = this.cues.find((entry) => entry.page === page);
        if (page === this.page || undefined === cue) {
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

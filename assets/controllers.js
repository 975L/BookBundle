import { startStimulusApp } from '@symfony/stimulus-bundle';
import ReaderController from './js/reader.js';

// Front-end controllers, used on public pages. Loaded as its own <script type="module"> tag (see the readme's "Install assets")
// Kebab-case identifier on purpose - Stimulus derives value attribute names from the identifier as registered, so a camelCase one silently breaks every "data-book-reader-*-value" binding
const app = startStimulusApp();
app.register('book-reader', ReaderController);

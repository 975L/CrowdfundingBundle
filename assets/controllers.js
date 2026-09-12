import { startStimulusApp } from '@symfony/stimulus-bundle';

// Front-end controllers, used on public pages. Loaded as its own <script type="module"> tag (see importmap.php), joining the one Stimulus application of the page rather than starting a second - see UiBundle's controllers.js
globalThis.c975lStimulusApp ??= startStimulusApp();
const app = globalThis.c975lStimulusApp;

// Dynamic import() so AssetMapper marks these lazy: an importmap entry, but no <link rel="modulepreload">
// Keys are the Stimulus identifiers as registered, matching what the templates write in data-controller
const LAZY_CONTROLLERS = {
    lottery: () => import('./js/lottery.js'),
};

const registered = new Set();

// Registers only the lazy controllers this document actually contains - the layout loads this barrel site-wide, while the draw wheel lives on the lottery page alone. Stimulus connects a controller as soon as it is registered, so a late registration still picks up elements already in the DOM
function registerPresentControllers() {
    for (const [identifier, load] of Object.entries(LAZY_CONTROLLERS)) {
        if (registered.has(identifier) || !document.querySelector(`[data-controller~="${identifier}"]`)) {
            continue;
        }

        registered.add(identifier);
        load().then((module) => app.register(identifier, module.default));
    }
}

registerPresentControllers();

// Turbo swaps the <body> without re-running this module, so a page reached by navigation would otherwise never get its own lazy controllers - a lottery reached from a campaign page would simply never answer the draw button
document.addEventListener('turbo:load', registerPresentControllers);

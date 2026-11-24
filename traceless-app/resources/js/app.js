import './bootstrap';
import './ambient-scene';
import { initLiveClocks } from './live-clock';

// Alpine.js is bundled and initialised by Livewire 3. Custom Alpine components
// are registered from Blade via `Alpine.data(...)` inside `alpine:init` when needed.

// Landing-page interactions are only loaded where they are used.
if (document.querySelector('[data-landing]')) {
    import('./landing-fx').then(({ initLandingEffects }) => {
        initLandingEffects();
    });
}
// Scan console (3D Earth, server-timed countdown) only where it is used.
if (document.querySelector('[data-scan-fx]')) {
    import('./scan-fx').then(({ initScanFx }) => {
        initScanFx();
    });
}

// Server-timed countdowns on dashboards and lists.
if (document.querySelector('[data-live-deadline]')) {
    initLiveClocks();
}

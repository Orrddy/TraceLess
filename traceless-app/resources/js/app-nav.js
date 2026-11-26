/**
 * App-wide navigation feedback.
 *
 * Authenticated-shell links are marked `wire:navigate` in Blade so clicking the
 * sidebar/dashboard does not reload CSS, JS and fonts. This module draws the
 * immediate progress bar for Livewire navigation and Livewire actions.
 */

let progress = null;
let progressTimer = null;
let progressActive = false;

const ensureProgress = () => {
    if (progress && document.body.contains(progress)) return progress;

    progress = document.querySelector('[data-nav-progress]');
    if (progress) return progress;

    progress = document.createElement('div');
    progress.className = 'tl-nav-progress';
    progress.setAttribute('aria-hidden', 'true');
    document.body.appendChild(progress);

    return progress;
};

const startProgress = () => {
    const bar = ensureProgress();
    progressActive = true;
    window.clearTimeout(progressTimer);
    bar.classList.remove('is-done');
    bar.classList.add('is-active');
    bar.style.transform = 'scaleX(0.08)';

    // Let the first paint happen before starting the long transition.
    window.requestAnimationFrame(() => {
        if (progressActive) bar.style.transform = 'scaleX(0.72)';
    });

    progressTimer = window.setTimeout(() => {
        if (progressActive) bar.style.transform = 'scaleX(0.9)';
    }, 1800);
};

const finishProgress = () => {
    if (!progress) return;
    progressActive = false;
    window.clearTimeout(progressTimer);
    progress.style.transform = 'scaleX(1)';
    progress.classList.remove('is-active');
    progress.classList.add('is-done');
    window.setTimeout(() => {
        progress?.classList.remove('is-done');
        if (progress) progress.style.transform = 'scaleX(0)';
    }, 280);
};

export function initAppNav() {
    document.addEventListener('livewire:navigating', startProgress);
    document.addEventListener('livewire:navigated', () => {
        finishProgress();
    });

    let commitHooked = false;
    const registerCommitFeedback = () => {
        if (commitHooked || !window.Livewire?.hook) return;
        commitHooked = true;
        window.Livewire.hook('commit', ({ succeed, fail }) => {
            // Every Livewire action gets the same immediate feedback as navigation.
            startProgress();
            const done = () => finishProgress();
            succeed(done);
            fail(done);
        });
    };

    if (window.Livewire?.hook) {
        registerCommitFeedback();
    } else {
        document.addEventListener('livewire:init', registerCommitFeedback);
    }

}

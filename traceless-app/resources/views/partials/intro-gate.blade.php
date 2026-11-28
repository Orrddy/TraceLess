<div class="intro-gate" data-intro-gate role="dialog" aria-modal="true" aria-label="TraceLess introduction">
    <video
        class="intro-gate-video"
        data-intro-video
        src="{{ asset('media/intro.mp4') }}"
        poster="{{ asset('media/intro-poster.jpg') }}"
        preload="none"
        playsinline
        disablepictureinpicture
        controlslist="nodownload noplaybackrate nofullscreen"
        aria-label="TraceLess introduction film"
    ></video>
    <div class="intro-gate-scrim" aria-hidden="true"></div>

    <div class="intro-gate-top">
        <div class="intro-gate-id">
            <span class="intro-gate-dot" aria-hidden="true"></span>
            <span class="intro-gate-kicker">TraceLess / Introduction</span>
        </div>
        <button
            type="button"
            class="intro-gate-sound"
            data-intro-sound
            aria-pressed="false"
            aria-label="Turn intro sound on"
            hidden
        >
            <span class="intro-gate-sound-icon" aria-hidden="true">◉</span>
            <span data-intro-sound-label>Sound off</span>
        </button>
    </div>

    <button type="button" class="intro-gate-play" data-intro-play hidden>Play intro</button>

    <div class="intro-gate-bottom" aria-hidden="true">
        <div class="intro-gate-track"><span data-intro-progress></span></div>
        <div class="intro-gate-meta">
            <span>Public sources only</span>
            <span class="intro-gate-meta-dot"></span>
            <span>Consent gated</span>
        </div>
    </div>

    <div class="intro-gate-buffering" aria-hidden="true"><span></span></div>
</div>

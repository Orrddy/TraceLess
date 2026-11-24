{{--
    Global page loader. A custom animation (e.g. a loading.io export) is used when
    one exists at public/brand/page-loader.gif|svg|webp|png; otherwise the brand
    mark + Nevera wordmark animate as the fallback.
--}}
@php
    $loaderAsset = null;
    foreach (['gif', 'svg', 'webp', 'png'] as $ext) {
        $candidate = 'brand/page-loader.'.$ext;
        if (file_exists(public_path($candidate))) {
            $loaderAsset = $candidate;
            break;
        }
    }
@endphp

<div class="page-loader" data-page-loader role="status" aria-live="polite" aria-label="Loading TraceLess">
    <div class="page-loader-inner">
        @if($loaderAsset)
            <img src="{{ asset($loaderAsset) }}" alt="" class="page-loader-media" width="128" height="128">
        @else
            <div class="page-loader-mark" aria-hidden="true">
                <img src="{{ asset('brand/traceless-mark.svg') }}" alt="" width="46" height="46">
                <span class="page-loader-ring"></span>
            </div>
        @endif
        <p class="page-loader-word font-brand">TRACELESS</p>
        <div class="page-loader-track" aria-hidden="true"><span></span></div>
        <p class="page-loader-copy">Preparing your privacy console…</p>
    </div>
</div>
<noscript><style>.page-loader { display: none !important; }</style></noscript>

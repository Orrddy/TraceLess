{{--
    Global page loader.

    A custom animation (a loading.io export, if you later obtain one) is used
    when present at public/brand/page-loader.gif|svg|webp|png. Otherwise the
    original TraceLess molecule-orbit spinner animates here.
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
            {{-- Original monochrome dot-globe loader with 3D orbit rings. --}}
            <div class="globe-loader" aria-hidden="true">
                <div class="globe-loader-scene">
                    <div class="globe-loader-orbits">
                        <span class="globe-orbit o1"><b></b></span>
                        <span class="globe-orbit o2"><b></b></span>
                        <span class="globe-orbit o3"><b></b></span>
                    </div>
                    <div class="globe-loader-dots">
                        @for($lat = -75; $lat <= 75; $lat += 15)
                            @for($lon = 0; $lon < 360; $lon += 15)
                                <i style="--lat: {{ $lat }}deg; --lon: {{ $lon }}deg;"></i>
                            @endfor
                        @endfor
                    </div>
                    <span class="globe-loader-core"></span>
                    <span class="globe-loader-sheen"></span>
                </div>
            </div>
        @endif

        <p class="page-loader-word font-brand">TRACELESS</p>
        <div class="page-loader-track" aria-hidden="true"><span></span></div>
        <p class="page-loader-copy">Preparing your privacy console…</p>
    </div>
</div>
<noscript><style>.page-loader { display: none !important; }</style></noscript>

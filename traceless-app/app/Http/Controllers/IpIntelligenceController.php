<?php

namespace App\Http\Controllers;

use App\Services\IpIntelligence\IpAddressNormalizer;
use App\Services\IpIntelligence\IpIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated visitor-intelligence endpoint for the landing page.
 *
 * The visitor IP always comes from $request->ip(), which already applies the
 * TRUSTED_PROXIES configuration in bootstrap/app.php. Arbitrary X-Forwarded-For
 * headers are therefore ignored unless the connecting proxy is trusted.
 */
class IpIntelligenceController extends Controller
{
    public function __invoke(Request $request, IpIntelligenceService $service): JsonResponse
    {
        $ip = IpAddressNormalizer::normalize((string) $request->ip());

        if ($ip === null) {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => 'A valid public IP address is required.',
                    'data' => null,
                ], 422)
                ->header('Cache-Control', 'no-store');
        }

        $result = $service->lookup($ip);

        // No provider credentials: nothing can be analysed, so say so instead of
        // caching an empty report or handing out "0 of 4 sources responded".
        $status = $result['status'] === 'unavailable' ? 503 : 200;

        return response()
            ->json($result, $status)
            ->header('Cache-Control', 'no-store');
    }
}

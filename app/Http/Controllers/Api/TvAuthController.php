<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TvDeviceCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TV sign-in by device code, after RFC 8628: the TV asks for a code, shows
 * the short one, and polls with the long one until the listener approves it
 * at /tv. Error responses use the RFC's error names so a stock device-flow
 * client understands them.
 */
class TvAuthController extends Controller
{
    public function deviceCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_name' => 'nullable|string|max:80',
        ]);

        [$code, $deviceCode] = TvDeviceCode::issue($data['device_name'] ?? null, $request->ip());

        $verificationUri = route('tv.link');

        return response()->json([
            'device_code' => $deviceCode,
            'user_code' => $code->displayUserCode(),
            'verification_uri' => $verificationUri,
            // For a QR code on the TV: scanning it pre-fills the code.
            'verification_uri_complete' => $verificationUri.'?code='.$code->displayUserCode(),
            'expires_in' => (int) config('security.tv.code_minutes', 10) * 60,
            'interval' => (int) config('security.tv.poll_interval', 5),
        ]);
    }

    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_code' => 'required|string|max:128',
        ]);

        $code = TvDeviceCode::findByDeviceCode($data['device_code']);

        if (! $code || $code->status === TvDeviceCode::STATUS_CONSUMED) {
            return $this->error('invalid_grant');
        }

        if ($code->status === TvDeviceCode::STATUS_DENIED) {
            return $this->error('access_denied');
        }

        if ($code->isExpired() && $code->status === TvDeviceCode::STATUS_PENDING) {
            return $this->error('expired_token');
        }

        $interval = (int) config('security.tv.poll_interval', 5);
        $tooSoon = $code->last_polled_at !== null
            && $code->last_polled_at->diffInSeconds(now()) < $interval;

        $code->forceFill(['last_polled_at' => now()])->save();

        if ($code->status === TvDeviceCode::STATUS_PENDING) {
            return $this->error($tooSoon ? 'slow_down' : 'authorization_pending');
        }

        // Approved. The approval itself was checked against expiry, so a code
        // approved in time can still be collected by a TV that polls late.
        $token = $code->exchangeForToken();

        if (! $token) {
            return $this->error('invalid_grant');
        }

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($code->user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /** Sign out from the TV: the token stops working everywhere at once. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out']);
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->getAvatarUrl(),
        ];
    }

    private function error(string $error): JsonResponse
    {
        return response()->json(['error' => $error], 400);
    }
}

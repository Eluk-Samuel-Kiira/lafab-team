<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyCountryApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $countryCode = $request->header('X-Country-Code');

        // Log the incoming request
        // Log::info('🔐 API Token Verification', [
        //     'token_present' => !empty($token),
        //     'country_code_present' => !empty($countryCode),
        //     'country_code' => $countryCode,
        //     'token_preview' => $token ? substr($token, 0, 20) . '...' : 'null',
        //     'ip' => $request->ip(),
        //     'url' => $request->fullUrl(),
        //     'method' => $request->method(),
        // ]);

        if (!$token) {
            Log::warning('❌ API token missing', [
                'country_code' => $countryCode,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'API token required'
            ], 401);
        }

        if (!$countryCode) {
            Log::warning('❌ Country code header missing', [
                'token_preview' => substr($token, 0, 20) . '...',
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Country code header required (X-Country-Code)'
            ], 401);
        }

        $countryCode = strtoupper($countryCode);
        $envKey = $countryCode . '_API_KEY';
        $validToken = env($envKey);

        // // Log the comparison details
        // Log::info('🔍 Token comparison', [
        //     'country_code' => $countryCode,
        //     'env_key' => $envKey,
        //     'env_key_exists' => !empty($validToken),
        //     'env_key_preview' => $validToken ? substr($validToken, 0, 20) . '...' : 'null',
        //     'token_preview' => substr($token, 0, 20) . '...',
        //     'token_length' => strlen($token),
        //     'env_key_length' => $validToken ? strlen($validToken) : 0,
        //     'matches' => $validToken && hash_equals($validToken, $token),
        // ]);

        if (!$validToken) {
            Log::error('❌ Environment key not found', [
                'env_key' => $envKey,
                'country_code' => $countryCode,
                'available_keys' => array_keys(array_filter($_ENV, function($key) {
                    return str_ends_with($key, '_API_KEY');
                }, ARRAY_FILTER_USE_KEY)),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid API token for country: ' . $countryCode,
                'debug' => 'Environment key ' . $envKey . ' not found'
            ], 401);
        }

        if (!hash_equals($validToken, $token)) {
            Log::error('❌ Token mismatch', [
                'country_code' => $countryCode,
                'env_key' => $envKey,
                'token_start' => substr($token, 0, 10),
                'valid_token_start' => substr($validToken, 0, 10),
                'token_length' => strlen($token),
                'valid_token_length' => strlen($validToken),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid API token for country: ' . $countryCode
            ], 401);
        }

        // Log::info('✅ API token verified successfully', [
        //     'country_code' => $countryCode,
        //     'ip' => $request->ip(),
        // ]);

        $request->merge(['country_code' => $countryCode]);

        return $next($request);
    }
}
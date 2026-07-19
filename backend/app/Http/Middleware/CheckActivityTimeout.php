<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\ActivityHelper;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class CheckActivityTimeout
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = auth('api')->user();
        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token sudah kedaluwarsa.',
                'code' => 'TOKEN_EXPIRED',
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid.',
                'code' => 'TOKEN_INVALID',
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan atau bermasalah.',
                'code' => 'TOKEN_ERROR',
            ], 401);
        }

        // Kalau sampai sini $user tetap null (tanpa exception apapun),
        // TOLAK secara eksplisit - jangan diloloskan diam-diam
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $jti = ActivityHelper::getCurrentJti();

        // DEBUG SEMENTARA
        // dd([
        //     'jti' => $jti,
        //     'cached_value' => \Illuminate\Support\Facades\Cache::get('activity:' . $jti),
        //     'now' => now()->toDateTimeString(),
        // ]);

        if (!$jti) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid.',
            ], 401);
        }

        if (ActivityHelper::isIdle($jti)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi telah berakhir karena tidak ada aktivitas. Silakan segarkan sesi Anda.',
                'code' => 'SESI_BERAKHIR',
            ], 419);
        }

        return $next($request);
    }
}

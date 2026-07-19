<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ActivityHelper
{
    // protected const IDLE_TIMEOUT_MINUTES = 1;
    protected const IDLE_TIMEOUT_SECONDS = 600;
    protected const CACHE_PREFIX = 'activity:';

    /**
     * Ambil jti (JWT ID) dari token yang sedang dipakai request ini
     */
    public static function getCurrentJti(): ?string
    {
        try {
            $payload = auth('api')->payload();
            return $payload->get('jti');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Simpan/update waktu aktivitas terakhir untuk token (jti) tertentu
     */
    public static function touch(string $jti): void
    {
        $key = self::CACHE_PREFIX . $jti;

        Cache::put(
            $key,
            now()->toDateTimeString(),
            now()->addSeconds(self::IDLE_TIMEOUT_SECONDS + 300)
        );
    }

    /**
     * Cek apakah token (jti) tertentu sudah idle lebih dari batas waktu
     */
    public static function isIdle(string $jti): bool
    {
        $lastActivityRaw = Cache::get(self::CACHE_PREFIX . $jti);

        if (!$lastActivityRaw) {
            return true;
        }

        $lastActivity = Carbon::parse($lastActivityRaw);

        // Parameter kedua (true) memaksa hasil absolut/positif,
        // karena default Carbon 3 sekarang signed (bisa negatif)
        $idleSeconds = now()->diffInSeconds($lastActivity, true);

        return $idleSeconds > self::IDLE_TIMEOUT_SECONDS;
    }

    public static function forget(string $jti): void
    {
        Cache::forget(self::CACHE_PREFIX . $jti);
    }
}

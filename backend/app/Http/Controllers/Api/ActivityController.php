<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Helpers\ActivityHelper;

class ActivityController extends Controller
{
    public function heartbeat()
    {
        $jti = ActivityHelper::getCurrentJti();

        if (!$jti) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 401);
        }

        // Kalau sudah idle duluan sebelum heartbeat sempat dikirim,
        // tetap tolak - jangan biarkan heartbeat "menghidupkan kembali" sesi yang sudah mati
        // if (ActivityHelper::isIdle($jti)) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Sesi telah berakhir karena tidak ada aktivitas.',
        //         'code' => 'SESI_BERAKHIR',
        //     ], 419);
        // }

        ActivityHelper::touch($jti);

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas tercatat',
        ], 200);
    }
}

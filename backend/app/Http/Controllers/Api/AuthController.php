<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ActivityHelper;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JwtAuth\Facades\JwtAuth;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Check user exist
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        // Check password
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        // Check user status
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'User tidak aktif',
            ], 401);
        }

        // Attempt login dan dapatkan token
        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password']
        ];

        $token = auth('api')->attempt($credentials);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
            ], 401);
        }

        // Ambil jti dari token yang baru saja diterbitkan, lalu catat aktivitas awal
        $jti = auth('api')->payload()->get('jti');

        ActivityHelper::touch($jti);


        // Create audit log
        // AuditLog::create([
        //     'user_id' => $user->id,
        //     'action' => 'LOGIN',
        //     'details' => [
        //         'email' => $user->email,
        //         'ip' => $request->ip(),
        //     ],
        //     'ip_address' => $request->ip(),
        // ]);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    public function me()
    {
         /** @var \App\Models\User|null $user */
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ], 200);
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 401);
            }

            $oldJti = ActivityHelper::getCurrentJti();

            // Safety net: validasi idle langsung di controller
            if ($oldJti && ActivityHelper::isIdle($oldJti)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi telah berakhir karena tidak ada aktivitas. Silakan login kembali.',
                    'code' => 'SESI_BERAKHIR',
                ], 419);
            }

            // Refresh token → ini menghasilkan jti BARU (token lama otomatis diblacklist oleh jwt-auth)
            $token = auth('api')->refresh();

            // Hapus record aktivitas token lama, lalu catat aktivitas untuk jti baru
            if ($oldJti) {
                ActivityHelper::forget($oldJti);
            }

            $newJti = auth('api')->setToken($token)->payload()->get('jti');

            ActivityHelper::touch($newJti);

            return response()->json([
                'success' => true,
                'message' => 'Token refresh berhasil',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => $user,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh gagal: ' . $e->getMessage(),
            ], 401);
        }
    }


    public function logout()
    {
        try {
            $user = auth('api')->user();

            if ($user) {
                $jti = ActivityHelper::getCurrentJti();

                // AuditLog::create([
                //     'user_id' => $user->id,
                //     'action' => 'LOGOUT',
                //     'details' => [
                //         'email' => $user->email,
                //     ],
                //     'ip_address' => request()->ip(),
                // ]);

                // Bersihkan record aktivitas untuk token ini
                if ($jti) {
                    ActivityHelper::forget($jti);
                }
            }

            // Logout
            auth('api')->logout();

            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout gagal: ' . $e->getMessage(),
            ], 401);
        }
    }
}

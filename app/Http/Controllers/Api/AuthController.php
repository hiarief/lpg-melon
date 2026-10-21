<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login API: cocok dengan web — memakai `username` (bukan email).
     * Mengembalikan session id + token supaya aplikasi Flutter bisa
     * memakai session cookie ATAU Bearer token.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt(['username' => $data['username'], 'password' => $data['password']])) {
            return $this->error('Username atau password salah.', 401);
        }

        $user = Auth::user();

        // Token hanya dibuat kalau Sanctum terpasang
        $token = null;
        if (method_exists($user, 'createToken')) {
            $token = $user->createToken('api')->plainTextToken;
        }

        $sessionId = null;
        try {
            $request->session()->regenerate();
            $sessionId = $request->session()->getId();
        } catch (\RuntimeException $e) {
            // StartArraySessionHandler tidak aktif di context API — abaikan.
        }

        return $this->ok([
            'token'      => $token,
            'user'       => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username],
            'session_id' => $sessionId,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->ok(['logged_out' => true]);
    }

    public function me(Request $request)
    {
        $user = $request->user() ?? Auth::user();

        if (! $user) {
            return $this->error('Belum login.', 401);
        }

        return $this->ok(['id' => $user->id, 'name' => $user->name, 'username' => $user->username]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user() ?? Auth::user();

        abort_if(! $user, 401, 'Belum login.');

        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return $this->error('Password saat ini salah.', 422);
        }

        $user->update(['password' => Hash::make($data['new_password'])]);

        return $this->ok(['changed' => true]);
    }
}

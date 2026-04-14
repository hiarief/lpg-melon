@extends('layouts.app')
@section('title','Ganti Password')
@section('content')
<div class="mt-4 max-w-md">
    <h1 class="text-xl font-bold mb-4">🔒 Ganti Password</h1>

    <form method="POST" action="{{ route('password.change') }}"
          class="bg-white rounded-lg shadow p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Password Saat Ini</label>
            <input type="password" name="current_password"
                   class="w-full border rounded px-3 py-2 @error('current_password') border-red-400 @enderror"
                   required>
            @error('current_password')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Password Baru</label>
            <input type="password" name="new_password"
                   class="w-full border rounded px-3 py-2"
                   placeholder="Minimal 6 karakter" required>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Konfirmasi Password Baru</label>
            <input type="password" name="new_password_confirmation"
                   class="w-full border rounded px-3 py-2" required>
        </div>

        <button type="submit"
                class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 font-medium w-full">
            Simpan Password Baru
        </button>
    </form>
</div>
@endsection

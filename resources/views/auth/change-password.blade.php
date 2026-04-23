@extends('layouts.app')
@section('title','Ganti Password')

@section('content')
<style>
    .page-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--text1);
        margin-bottom: 12px;
    }

</style>

<div class="page-title">🔒 Ganti Password</div>

<div class="s-card" style="max-width:400px;">
    <form method="POST" action="{{ route('password.change') }}">
        @csrf

        <div style="display:flex;flex-direction:column;gap:12px;padding:14px;">

            <div>
                <label class="field-label">Password Saat Ini</label>
                <input type="password" name="current_password" class="field-input @error('current_password') is-error @enderror" required>
                @error('current_password')
                <div style="font-size:11px;color:var(--danger,#dc2626);margin-top:4px;">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="field-label">Password Baru</label>
                <input type="password" name="new_password" class="field-input" placeholder="Minimal 6 karakter" required>
            </div>

            <div>
                <label class="field-label">Konfirmasi Password Baru</label>
                <input type="password" name="new_password_confirmation" class="field-input" required>
            </div>

            <div style="padding-top:4px;">
                <button type="submit" class="btn-primary" style="width:100%;padding:10px;font-size:13px;">
                    Simpan Password Baru
                </button>
            </div>

        </div>
    </form>
</div>
@endsection

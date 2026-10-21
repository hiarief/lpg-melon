<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Ambil periode dari query `period_id`, fallback ke periode aktif.
     * Dipakai bersama oleh semua controller API.
     */
    protected function resolvePeriod(Request $request): \App\Models\Period
    {
        $periodId = $request->query('period_id') ?? $request->input('period_id');

        if ($periodId) {
            return \App\Models\Period::findOrFail((int) $periodId);
        }

        return \App\Models\Period::current()
            ?? \App\Models\Period::orderByDesc('year')->orderByDesc('month')->firstOrFail();
    }

    /**
     * Format respons API konsisten untuk aplikasi Flutter:
     *   { "success": true, "data": ... }
     */
    protected function ok(mixed $data = null, int $status = 200)
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
        ], $status);
    }

    protected function error(string $message, int $status = 422, ?array $errors = null)
    {
        $payload = ['success' => false, 'error' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}

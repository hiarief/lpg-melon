<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodResource;
use App\Models\Period;
use App\Services\PeriodSummaryService;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function __construct(private PeriodSummaryService $summary) {}

    /** Daftar periode + periode aktif (untuk dropdown di aplikasi). */
    public function periods()
    {
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();
        $current = Period::current();

        return $this->ok([
            'current_period_id' => $current?->id,
            'current_period'    => $current ? new PeriodResource($current) : null,
            'periods'           => PeriodResource::collection($periods),
        ]);
    }

    /** Ringkasan finansial lengkap satu periode. */
    public function show(Request $request)
    {
        $periodId = $request->query('period_id', Period::current()?->id);
        $period = Period::findOrFail($periodId);

        return $this->ok($this->summary->build($period));
    }

    /** Hanya blok KPI (ringan, untuk auto-refresh di dashboard). */
    public function kpi(Request $request)
    {
        $periodId = $request->query('period_id', Period::current()?->id);
        $period = Period::findOrFail($periodId);

        return $this->ok($this->summary->build($period)['kpi']);
    }
}

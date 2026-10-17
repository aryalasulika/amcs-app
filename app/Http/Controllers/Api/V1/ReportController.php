<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ArraySheetExport;
use App\Exports\IntegratedReportExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\MovementLogResource;
use App\Http\Resources\MovementResource;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function daily(Request $request): JsonResponse
    {
        $this->authorizeView();

        $date = (string) $request->query('date', CarbonImmutable::now()->toDateString());
        $data = $this->reports->dailyReport($date);

        return response()->json([
            'date' => $data['date'],
            'totals' => $data['totals'],
            'per_stand' => $data['per_stand'],
            'movements' => MovementResource::collection($data['movements'])->resolve(),
        ]);
    }

    public function monthly(Request $request): JsonResponse
    {
        $this->authorizeView();

        $year = (int) $request->query('year', CarbonImmutable::now()->year);
        $month = (int) $request->query('month', CarbonImmutable::now()->month);

        return response()->json($this->reports->monthlyReport($year, $month));
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorizeView();

        $year = $request->filled('year') ? (int) $request->query('year') : null;

        $filters = $request->only([
            'date', 'date_from', 'date_to', 'flight_number',
            'parking_stand_id', 'airline_id', 'state', 'flight_type', 'user_id',
        ]);

        return response()->json([
            'year' => $year,
            'totals' => $this->reports->filteredTotals($filters),
            'periods' => $this->reports->yearlySummary($year),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $this->authorizeView();

        $filters = $request->only([
            'date', 'date_from', 'date_to', 'flight_number',
            'parking_stand_id', 'airline_id', 'state', 'flight_type', 'user_id',
        ]);

        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);

        $paginator = $this->reports->history($filters, $perPage);

        return response()->json([
            'data' => MovementResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $this->authorizeView();

        $filters = $request->only(['date', 'date_from', 'date_to', 'stand_code', 'flight_number']);
        $perPage = min(max((int) $request->integer('per_page', 50), 1), 200);

        $paginator = $this->reports->activityLog($filters, $perPage);

        return response()->json([
            'data' => MovementLogResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('reports.export'), 403, 'Anda tidak berwenang mengekspor laporan.');

        $filters = $request->only([
            'date', 'date_from', 'date_to', 'flight_number',
            'parking_stand_id', 'airline_id', 'state', 'flight_type', 'user_id',
        ]);

        $format = strtolower((string) $request->query('format', 'xlsx'));
        $filename = config('amcs.export.filename').'_'.CarbonImmutable::now()->format('Ymd_His');

        $detailRows = $this->reports->exportRows($filters);
        $detailHeadings = array_shift($detailRows) ?? [];

        if ($format === 'csv') {
            return Excel::download(
                new ArraySheetExport($detailRows, $detailHeadings, 'Detail'),
                $filename.'.csv',
                ExcelWriter::CSV
            );
        }

        $recapRows = [['PERIODE', 'TOTAL PERGERAKAN', 'TOTAL DELAY', 'TOTAL RON', 'TOTAL MENIT DELAY']];
        foreach ($this->reports->yearlySummary() as $period) {
            $recapRows[] = [
                $period['label'],
                $period['movements'],
                $period['delays'],
                $period['ron'],
                $period['total_delay_minutes'],
            ];
        }

        return Excel::download(
            new IntegratedReportExport($detailRows, $recapRows),
            $filename.'.xlsx'
        );
    }

    protected function authorizeView(): void
    {
        abort_unless(request()->user()?->can('reports.view'), 403, 'Anda tidak berwenang melihat laporan.');
    }
}

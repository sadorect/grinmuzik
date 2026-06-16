<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

class RevenueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->input('period', 'month');

        $from = match ($period) {
            'day'   => now()->subDay(),
            'week'  => now()->subWeek(),
            default => now()->subMonth(),
        };

        if (! Schema::hasTable('donations')) {
            return response()->json([
                'note'   => 'No revenue/donations table found. Implement a donations table to track revenue.',
                'period' => $period,
                'total'  => 0,
            ]);
        }

        $total = DB::table('donations')
            ->where('created_at', '>=', $from)
            ->where('status', 'completed')
            ->sum('amount');

        $byDay = DB::table('donations')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $from)
            ->where('status', 'completed')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'period'       => $period,
            'from'         => $from->toDateString(),
            'total'        => (float) $total,
            'breakdown'    => $byDay,
        ]);
    }
}

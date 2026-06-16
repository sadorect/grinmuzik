<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function status(): JsonResponse
    {
        $pending = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->get()
            ->keyBy('queue')
            ->map(fn ($row) => (int) $row->count);

        $failed = DB::table('failed_jobs')
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->get()
            ->keyBy('queue')
            ->map(fn ($row) => (int) $row->count);

        $queues = $pending->keys()->merge($failed->keys())->unique()->sort()->values();

        $result = $queues->map(fn ($queue) => [
            'queue'   => $queue,
            'pending' => $pending->get($queue, 0),
            'failed'  => $failed->get($queue, 0),
        ]);

        return response()->json([
            'total_pending' => $pending->sum(),
            'total_failed'  => $failed->sum(),
            'queues'        => $result->values(),
        ]);
    }
}

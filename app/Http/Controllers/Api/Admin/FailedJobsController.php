<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FailedJobsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 20), 100);

        $jobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn ($job) => [
                'id'         => $job->id,
                'queue'      => $job->queue,
                'failed_at'  => $job->failed_at,
                'exception'  => str($job->exception)->limit(300)->toString(),
                'payload'    => json_decode($job->payload, true)['displayName'] ?? null,
            ]);

        return response()->json([
            'total'  => DB::table('failed_jobs')->count(),
            'jobs'   => $jobs,
        ]);
    }
}

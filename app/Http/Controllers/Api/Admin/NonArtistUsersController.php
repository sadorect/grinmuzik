<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NonArtistUsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(200, (int) $request->input('limit', 50)));

        $users = User::query()
            ->whereDoesntHave('artistProfile')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at'        => $user->created_at,
            ]);

        return response()->json([
            'count' => $users->count(),
            'users' => $users,
        ]);
    }
}

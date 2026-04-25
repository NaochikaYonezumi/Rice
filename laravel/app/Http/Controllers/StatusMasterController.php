<?php

namespace App\Http\Controllers;

use App\Models\StatusMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusMasterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(StatusMaster::orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:status_masters',
            'key'  => 'required|string|max:50|unique:status_masters',
            'color' => 'nullable|string|max:20',
        ]);

        $status = StatusMaster::create($validated);
        return response()->json($status);
    }
}

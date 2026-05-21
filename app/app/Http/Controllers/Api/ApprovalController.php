<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Approval;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = Approval::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(30));
    }

    public function show(string $id)
    {
        return response()->json(Approval::findOrFail($id));
    }

    public function approve(string $id, Request $request)
    {
        $approval = Approval::findOrFail($id);

        $approval->update([
            'status'       => 'approved',
            'decision_note'=> $request->input('note'),
            'decided_at'   => now(),
        ]);

        return response()->json(['message' => 'Approval granted.', 'approval' => $approval]);
    }

    public function reject(string $id, Request $request)
    {
        $approval = Approval::findOrFail($id);

        $approval->update([
            'status'        => 'rejected',
            'decision_note' => $request->input('note'),
            'decided_at'    => now(),
        ]);

        return response()->json(['message' => 'Approval rejected.', 'approval' => $approval]);
    }
}

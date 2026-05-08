<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Checklist;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function index(Request $request)
    {
        $q = Checklist::query()->where('is_active', true);
        if ($s = $request->query('q')) {
            $q->where('name', 'ilike', '%'.$s.'%');
        }

        return $q->orderBy('name')->paginate(40);
    }

    public function show(string $id)
    {
        return Checklist::query()->findOrFail($id);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Playbook;
use Illuminate\Http\Request;

class PlaybookController extends Controller
{
    public function index(Request $request)
    {
        $q = Playbook::query()->where('is_active', true);
        if ($s = $request->query('q')) {
            $q->where('name', 'ilike', '%'.$s.'%');
        }

        return $q->orderBy('name')->paginate(40);
    }

    public function show(string $id)
    {
        return Playbook::query()->findOrFail($id);
    }
}

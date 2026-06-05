<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Rule;
use Illuminate\Http\Request;

class RuleController extends Controller
{
    public function index(Request $request)
    {
        $q = Rule::query()->where('is_active', true);
        if ($s = $request->query('scope')) {
            $q->where('scope', $s);
        }
        if ($s = $request->query('q')) {
            $q->where('name', 'ilike', '%'.$s.'%');
        }

        return $q->orderByDesc('priority')->paginate(50);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'rule_text' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer',
        ]);

        /** @var Rule $r */
        $r = Rule::query()->findOrFail($id);
        $r->update($data);

        return $r->fresh();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Skill;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index(Request $request)
    {
        $q = Skill::query();
        if ($request->boolean('active_only')) {
            $q->where('is_active', true);
        }
        if ($s = $request->query('q')) {
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'ilike', '%'.$s.'%')
                    ->orWhere('description', 'ilike', '%'.$s.'%');
            });
        }
        if ($c = $request->query('category')) {
            $q->where('name', 'ilike', '%'.$c.'%');
        }

        return $q->with('links')->orderBy('name')->paginate(60);
    }

    public function show(string $id)
    {
        return Skill::query()->with('links')->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'is_active' => 'sometimes|boolean',
            'description' => 'sometimes|string',
        ]);

        /** @var Skill $s */
        $s = Skill::query()->findOrFail($id);
        $s->update($data);

        return $s->fresh();
    }
}

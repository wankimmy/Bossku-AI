<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Assistant;
use App\Services\Kernel\Graph\GraphRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Assistants: saved, runnable graph configurations (LangGraph-server parity).
 */
class AssistantController extends Controller
{
    public function __construct(private readonly GraphRegistry $graphs) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => Assistant::query()->latest()->get()]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(Assistant::findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:bossku_ai_assistants,slug'],
            'graph' => ['sometimes', 'string', Rule::in($this->graphs->names())],
            'config' => ['sometimes', 'array'],
            'description' => ['sometimes', 'nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        $data['graph'] = $data['graph'] ?? 'default_pipeline';

        $assistant = Assistant::query()->create($data);

        return response()->json($assistant, 201);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $assistant = Assistant::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('bossku_ai_assistants', 'slug')->ignore($assistant->id)],
            'graph' => ['sometimes', 'string', Rule::in($this->graphs->names())],
            'config' => ['sometimes', 'array'],
            'description' => ['sometimes', 'nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $assistant->update($data);

        return response()->json($assistant);
    }

    public function destroy(string $id): JsonResponse
    {
        Assistant::findOrFail($id)->delete();

        return response()->json(['message' => 'Assistant deleted.']);
    }
}

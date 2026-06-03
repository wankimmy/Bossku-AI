<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Memory;
use App\Services\BosskuAi\UserProfileService;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function __construct(
        protected UserProfileService $profiles
    ) {}

    public function show()
    {
        $profile = $this->profiles->get();

        return response()->json([
            'profile' => $profile ? $this->present($profile) : null,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'content' => 'required|string|max:20000',
            'headline' => 'sometimes|nullable|string|max:500',
        ]);

        $profile = $this->profiles->save($data['content'], $data['headline'] ?? null, [], 'manual');

        return response()->json(['profile' => $this->present($profile)]);
    }

    public function generate()
    {
        try {
            $profile = $this->profiles->generate();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not generate the profile: '.$e->getMessage(),
            ], 422);
        }

        return response()->json(['profile' => $this->present($profile)]);
    }

    /** @return array<string,mixed> */
    protected function present(Memory $m): array
    {
        $meta = is_array($m->metadata) ? $m->metadata : [];

        return [
            'id' => $m->getKey(),
            'headline' => $m->human_summary,
            'content' => $m->content,
            'origin' => $meta['origin'] ?? null,
            'generated_by_model' => $meta['generated_by_model'] ?? null,
            'confidence' => $m->confidence,
            'updated_at' => $m->updated_at,
        ];
    }
}

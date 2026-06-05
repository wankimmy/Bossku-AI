<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BosskuSkillsApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function skills_index_returns_success(): void
    {
        $this->getJson('/api/skills')->assertSuccessful();
    }
}

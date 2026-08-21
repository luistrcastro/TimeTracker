<?php

namespace Tests\Feature\Replicon;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_create_succeeds_with_only_project_and_date(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/replicon/entries', [
                'date'    => '2026-01-15',
                'project' => 'PROJ',
            ])
            ->assertCreated();

        $response->assertJsonPath('project', 'PROJ')
                  ->assertJsonPath('description', '')
                  ->assertJsonPath('start', null)
                  ->assertJsonPath('finish', null)
                  ->assertJsonPath('durationMinutes', 0);
    }

    public function test_create_fails_without_project(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/replicon/entries', [
                'date' => '2026-01-15',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project']);
    }

    public function test_update_can_leave_finish_and_description_unset(): void
    {
        $entry = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/replicon/entries', [
                'date'    => '2026-01-15',
                'project' => 'PROJ',
                'start'   => '08:00',
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/replicon/entries/{$entry['id']}", [
                'subDescription' => 'in progress',
            ])
            ->assertOk()
            ->assertJsonPath('finish', null)
            ->assertJsonPath('durationMinutes', 0)
            ->assertJsonPath('subDescription', 'in progress');
    }

    public function test_update_fails_when_clearing_project(): void
    {
        $entry = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/replicon/entries', [
                'date'    => '2026-01-15',
                'project' => 'PROJ',
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/replicon/entries/{$entry['id']}", [
                'project' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project']);
    }
}

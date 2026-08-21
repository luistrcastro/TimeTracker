<?php

namespace Tests\Feature\Replicon;

use App\Models\RepliconProject;
use App\Models\RepliconTask;
use App\Models\User;
use App\Services\Replicon\RepliconClient;
use App\Services\Replicon\RepliconSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncDeactivatesStaleProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_marks_missing_projects_and_tasks_inactive_without_deleting_them(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $staleProject = RepliconProject::forceCreate([
            'user_id'     => $user->id,
            'replicon_id' => 'OLD-1',
            'code'        => 'OLD',
            'name'        => 'OLD - Old Project',
            'is_active'   => true,
        ]);
        $staleTask = RepliconTask::forceCreate([
            'replicon_project_id' => $staleProject->id,
            'replicon_task_id'    => 'OLD-TASK-1',
            'name'                => 'Old Task',
            'path'                => ['Old Task'],
            'is_active'           => true,
        ]);

        $client = Mockery::mock(RepliconClient::class);
        $client->shouldReceive('queueRequests')
            ->once()
            ->with(Mockery::on(fn($reqs) => $reqs[0]['methodName'] === 'RequestProjects'))
            ->andReturn([
                'd' => ['data' => [[
                    'RequestIndex'    => 1,
                    'CommitRequests'  => [[
                        'ReturnObject' => [
                            'Projects'     => [['Value' => 'NEW-1', 'Text' => 'NEW - New Project']],
                            'TotalOptions' => 1,
                        ],
                    ]],
                ]]],
            ]);

        $client->shouldReceive('queueRequests')
            ->once()
            ->with(Mockery::on(fn($reqs) => $reqs[0]['methodName'] === 'RequestTasks'))
            ->andReturn([
                'd' => ['data' => [[
                    'RequestIndex'   => 1,
                    'CommitRequests' => [[
                        'ReturnObject' => [
                            'RootTask' => [
                                'ChildTasks' => [
                                    ['Value' => 'NEW-TASK-1', 'Text' => 'New Task'],
                                ],
                            ],
                        ],
                    ]],
                ]]],
            ]);

        (new RepliconSyncService($client))->sync($user);

        $this->assertFalse($staleProject->fresh()->is_active);
        $this->assertFalse($staleTask->fresh()->is_active);

        $newProject = RepliconProject::where('replicon_id', 'NEW-1')->first();
        $this->assertNotNull($newProject);
        $this->assertTrue($newProject->is_active);

        $newTask = RepliconTask::where('replicon_task_id', 'NEW-TASK-1')->first();
        $this->assertNotNull($newTask);
        $this->assertTrue($newTask->is_active);
    }
}

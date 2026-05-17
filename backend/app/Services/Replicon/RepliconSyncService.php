<?php

namespace App\Services\Replicon;

use App\Models\RepliconProject;
use App\Models\RepliconTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RepliconSyncService
{
    public function __construct(private RepliconClient $client) {}

    /**
     * Sync all projects and tasks from Replicon into the database.
     *
     * Uses the same paged QueueRequests approach as the Python server:
     *   - RequestProjects (paged by requestIndex page offset)
     *   - RequestTasks batched for all projects in one call
     *
     * @return array{ projects: int, tasks: int }
     */
    public function sync(User $user): array
    {
        $projectsRaw = [];
        $page        = 0;
        $synced      = ['projects' => 0, 'tasks' => 0];

        // ── Fetch all projects (paged) ──────────────────────────────
        while (true) {
            $resp = $this->client->queueRequests([[
                'requestIndex' => 1,
                'methodName'   => 'RequestProjects',
                'instanceId'   => 'timesheet',
                'paramList'    => [(string) $page],
            ]]);

            Log::debug('Replicon RequestProjects raw response', ['resp' => $resp]);
            $ret = $this->getReturnObject($resp, 1);
            if (!$ret) {
                break;
            }

            $batch = $ret['Projects'] ?? [];
            array_push($projectsRaw, ...$batch);

            $total = $ret['TotalOptions'] ?? count($projectsRaw);
            if (count($projectsRaw) >= $total || empty($batch)) {
                break;
            }
            $page++;
        }
        Log::warning($projectsRaw);
        if (empty($projectsRaw)) {
            return $synced;
        }

        // ── Batch fetch tasks for all projects ──────────────────────
        $taskRequests = [];
        foreach ($projectsRaw as $i => $proj) {
            $taskRequests[] = [
                'requestIndex' => $i + 1,
                'methodName'   => 'RequestTasks',
                'instanceId'   => 'timesheet',
                'paramList'    => [$proj['Value']],
            ];
        }

        $taskResp    = $this->client->queueRequests($taskRequests);
        $taskResults = [];
        foreach ($projectsRaw as $i => $proj) {
            $ret = $this->getReturnObject($taskResp, $i + 1);
            if ($ret) {
                $leaves = [];
                $this->extractLeafTasks($ret['RootTask'] ?? [], $leaves, []);
                $taskResults[$i] = $leaves;
            }
        }

        // ── Persist projects and tasks ───────────────────────────────
        foreach ($projectsRaw as $i => $proj) {
            $text = $proj['Text'] ?? '';
            $code = str_contains($text, ' - ')
                ? trim(explode(' - ', $text)[0])
                : $text;

            $project = RepliconProject::updateOrCreate(
                ['user_id' => $user->id, 'replicon_id' => (string) $proj['Value']],
                [
                    'code'      => $code,
                    'name'      => $text,
                    'synced_at' => Carbon::now(),
                ]
            );
            $synced['projects']++;

            foreach ($taskResults[$i] ?? [] as ['task' => $task, 'path' => $path]) {
                RepliconTask::updateOrCreate(
                    [
                        'replicon_project_id' => $project->id,
                        'replicon_task_id'    => (string) $task['Value'],
                    ],
                    [
                        'name' => $task['Text'] ?? '',
                        'path' => $path,
                    ]
                );
                $synced['tasks']++;
            }
        }

        return $synced;
    }

    /**
     * Recursively collect leaf tasks (no ChildTasks) with their ancestor path.
     *
     * Critical rule from CLAUDE.md:
     *   - Always recurse into category/folder nodes regardless of their Enabled status
     *   - Only leaf tasks (nodes with no ChildTasks) are appended
     *   - Do NOT gate recursion on Enabled — category nodes may be disabled but have enabled children
     */
    private function extractLeafTasks(array $rootTask, array &$leaves, array $path): void
    {
        foreach ($rootTask['ChildTasks'] ?? [] as $task) {
            $name     = $task['Text'] ?? '';
            $taskPath = array_merge($path, [$name]);
            $children = $task['ChildTasks'] ?? [];

            if (!empty($children)) {
                // Always recurse — never skip based on Enabled
                $this->extractLeafTasks($task, $leaves, $taskPath);
            } else {
                // Leaf task — append if it has a Value (id)
                if (!empty($task['Value'])) {
                    $leaves[] = ['task' => $task, 'path' => $taskPath];
                }
            }
        }
    }

    /**
     * Extract ReturnObject for a given requestIndex from a QueueRequests response.
     * Response shape: d.data[N] where data[N].RequestIndex == requestIndex
     *   → CommitRequests[0].ReturnObject
     */
    private function getReturnObject(array $resp, int $requestIndex): ?array
    {
        try {
            foreach ($resp['d']['data'] ?? [] as $item) {
                if (($item['RequestIndex'] ?? null) === $requestIndex) {
                    return $item['CommitRequests'][0]['ReturnObject'] ?? null;
                }
            }
        } catch (\Throwable) {
            // Malformed response
        }
        return null;
    }
}

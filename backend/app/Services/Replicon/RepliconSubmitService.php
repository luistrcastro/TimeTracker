<?php

namespace App\Services\Replicon;

use App\Models\RepliconCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RepliconSubmitService
{
    public function __construct(private RepliconClient $client) {}

    /**
     * Submit hours and comments to Replicon for the given rows and date.
     *
     * Type quirks preserved from Python (docs/replicon-api.md):
     *   SetDuration paramList[2] = (string) $col
     *   SetComment  paramList[2] = (int)    $col
     *
     * QueueRequests structure (from server.py _handle_replicon_submit):
     *   { requestIndex, methodName, instanceId: 'timesheet', paramList: ['time', rowId, col, value] }
     *
     * The last element is always a Save request with an empty paramList.
     *
     * @param  User    $user
     * @param  array   $rows  Each: { projectId, taskId, rowIndex, hours, comment? }
     * @param  string  $date  Y-m-d
     * @return array
     */
    public function submit(User $user, array $rows, string $date): array
    {
        $col      = $this->client->columnForDate($date);
        $requests = [];
        $results  = [];
        $rowIndices = []; // track (durIdx, cmtIdx) per row for commit-map parsing

        // Fetch the current last_request_index to continue the counter
        $cred = $this->client->credential();
        $idx  = $cred->last_request_index + 1;

        foreach ($rows as $row) {
            $projectId = $row['projectId'];
            $taskId    = $row['taskId'];
            $rowIndex  = (int) $row['rowIndex'];
            $hours     = (float) $row['hours'];
            $comment   = $row['comment'] ?? '';

            // SetDuration: col passed as string (Replicon API type quirk)
            $requests[] = [
                'requestIndex' => $idx,
                'methodName'   => 'SetDuration',
                'instanceId'   => 'timesheet',
                'paramList'    => ['time', (string) $rowIndex, (string) $col, (string) $hours],
            ];

            $durIdx = $idx;
            $cmtIdx = null;
            $idx++;

            // SetComment: col passed as int (Replicon API type quirk)
            if ($comment !== '') {
                $requests[] = [
                    'requestIndex' => $idx,
                    'methodName'   => 'SetComment',
                    'instanceId'   => 'timesheet',
                    'paramList'    => ['time', (string) $rowIndex, (int) $col, $comment],
                ];
                $cmtIdx = $idx;
                $idx++;
            }

            $rowIndices[] = [$durIdx, $cmtIdx];
            $results[]    = [
                'projectId' => $projectId,
                'taskId'    => $taskId,
                'ok'        => true,
            ];
        }

        if (empty($requests)) {
            return $results;
        }

        // Always append Save at the end
        $saveIdx    = $idx;
        $requests[] = [
            'requestIndex' => $saveIdx,
            'methodName'   => 'Save',
            'instanceId'   => 'timesheet',
            'paramList'    => [],
        ];

        $resp = $this->client->queueRequests($requests);

        // Build commit map: requestIndex → bool (CommitRequests non-empty)
        $commitMap = [];
        foreach ($resp['d']['data'] ?? [] as $item) {
            $ri            = $item['RequestIndex'] ?? null;
            $commitMap[$ri] = !empty($item['CommitRequests']);
        }

        // Update per-row results based on actual Replicon commit status
        foreach ($rowIndices as $i => [$durIdx, $cmtIdx]) {
            $durOk = $commitMap[$durIdx] ?? false;
            $cmtOk = $cmtIdx === null || ($commitMap[$cmtIdx] ?? false);

            if (!$durOk && !$cmtOk) {
                $results[$i]['ok']    = false;
                $results[$i]['error'] = 'Not committed by Replicon — session may have expired';
            } elseif (!$durOk) {
                $results[$i]['ok']    = false;
                $results[$i]['error'] = 'Duration not committed';
            } elseif (!$cmtOk) {
                $results[$i]['ok']    = false;
                $results[$i]['error'] = 'Comment not committed';
            }
        }

        // Persist the last used requestIndex so next submit continues the counter
        DB::transaction(function () use ($saveIdx, $cred) {
            RepliconCredential::where('user_id', $cred->user_id)
                ->lockForUpdate()
                ->update(['last_request_index' => $saveIdx]);
        });

        return $results;
    }
}

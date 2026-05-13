<?php

namespace App\Services\Replicon;

use App\Models\RepliconCredential;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RepliconClient
{
    private RepliconCredential $cred;

    public function __construct(private User $user)
    {
        $this->cred = RepliconCredential::where('user_id', $user->id)->firstOrFail();
    }

    /**
     * Send a QueueRequests call to the Replicon timesheet service.
     *
     * @param  array  $requests  Array of { requestIndex, methodName, instanceId, paramList }
     * @param  bool   $retry     Whether to retry on Action 11 session redirect
     */
    public function queueRequests(array $requests, bool $retry = true): array
    {
        $index = DB::transaction(function () {
            $fresh = RepliconCredential::where('user_id', $this->user->id)->lockForUpdate()->first();
            $idx   = $fresh->last_request_index + 1;
            $fresh->update(['last_request_index' => $idx]);
            return $idx;
        });

        $payload = [
            'serverViewStateId' => $this->cred->server_view_state_id,
            'sessionId'         => $this->cred->session_id,
            'requests'          => $requests,
        ];

        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type'     => 'application/json',
                'Accept'           => 'application/json',
                'Cookie'           => $this->cred->cookie_header,
                'Origin'           => rtrim($this->cred->base_url, '/'),
                'Referer'          => rtrim($this->cred->base_url, '/') . '/a/TimeSheetModule/TimeSheet.aspx',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(
                rtrim($this->cred->base_url, '/') . '/a/TimesheetService/Interaction.asmx/QueueRequests',
                $payload
            );

        $body = $response->json() ?? [];

        // Action 11: session expired redirect — parse new sessionId and retry once
        $newSession = $this->extractRedirectedSession($body);
        if ($newSession && $retry) {
            $this->cred->refresh();
            // Rebuild the cookie header with the rotated session id
            $newCookie = preg_replace(
                '/ASP\.NET_SessionId=[^;]+/',
                "ASP.NET_SessionId={$newSession}",
                $this->cred->cookie_header
            );
            $this->cred->update([
                'session_id'    => $newSession,
                'cookie_header' => $newCookie,
            ]);
            $this->cred->refresh();

            return $this->queueRequests($requests, false);
        }

        return $body;
    }

    /**
     * Compute the Replicon column index for a given date.
     *
     * Formula from Python: col = (date.weekday() + 2) % 7
     * Python weekday(): Mon=0 … Sun=6
     * Maps to: Sat=0, Sun=1, Mon=2, Tue=3, Wed=4, Thu=5, Fri=6
     *
     * Carbon dayOfWeek: Sun=0, Mon=1 … Sat=6
     * Carbon dayOfWeekIso: Mon=1 … Sun=7
     *
     * Using the Python-equivalent mapping:
     *   Carbon dayOfWeek Sun=0 → Python weekday 6 → col (6+2)%7 = 1
     *   Carbon dayOfWeek Mon=1 → Python weekday 0 → col (0+2)%7 = 2
     * So: col = (Carbon::parse($date)->dayOfWeek + 6 + 2) % 7
     *       = (Carbon::parse($date)->dayOfWeek + 8) % 7
     *       = (Carbon::parse($date)->dayOfWeek + 1) % 7
     */
    public function columnForDate(string $date): int
    {
        // Carbon dayOfWeek: Sun=0, Mon=1, Tue=2, Wed=3, Thu=4, Fri=5, Sat=6
        // Python weekday:   Mon=0, Tue=1, Wed=2, Thu=3, Fri=4, Sat=5, Sun=6
        // Python formula: col = (weekday + 2) % 7
        // Carbon Sun(0) == Python Sun(6): (6+2)%7 = 1
        // Carbon Mon(1) == Python Mon(0): (0+2)%7 = 2
        // Map: carbonDow → pythonWeekday
        //   Sun=0 → 6, Mon=1 → 0, Tue=2 → 1, Wed=3 → 2, Thu=4 → 3, Fri=5 → 4, Sat=6 → 5
        // pythonWeekday = (carbonDow + 6) % 7
        $carbonDow = Carbon::parse($date)->dayOfWeek; // Sun=0..Sat=6
        $pythonWeekday = ($carbonDow + 6) % 7;        // Mon=0..Sun=6
        return ($pythonWeekday + 2) % 7;              // Sat=0,Sun=1,Mon=2..Fri=6
    }

    public function credential(): RepliconCredential
    {
        return $this->cred;
    }

    /**
     * Extract the new sessionId from an Action 11 session redirect response.
     * Content shape: {sessionId:'0f21d16e-...'}
     */
    private function extractRedirectedSession(array $response): ?string
    {
        try {
            foreach ($response['d']['data'] ?? [] as $item) {
                foreach ($item['Actions'] ?? [] as $action) {
                    if (($action['Action'] ?? null) === 11) {
                        if (preg_match("/sessionId:'([^']+)'/", $action['Content'] ?? '', $m)) {
                            return $m[1];
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Malformed response — no redirect
        }
        return null;
    }
}

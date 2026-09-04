<?php

namespace Modules\ReportCard\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\ReportCard\Jobs\SendReportCardJob;
use Modules\ReportCard\Models\ReportCard;

/**
 * Finds report cards ready to go out and queues one job per card. Shared by
 * the nightly command and the "send all ready" button, so a manual bulk
 * send queues exactly what the background schedule would have.
 */
class ReportCardQueuer
{
    /**
     * @param  Builder<ReportCard>  $query  A ReportCard query already scoped to
     *                                      whatever the caller is allowed to send.
     */
    public function queueReady(Builder $query): int
    {
        $ids = (clone $query)
            ->where('status', 'ready')
            ->where('completed_at', '<=', now()->subDay())
            ->pluck('id');

        foreach ($ids as $id) {
            SendReportCardJob::dispatch($id);
        }

        return $ids->count();
    }
}

<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogService
{
    /**
     * Log an activity record.
     *
     * @param string $action
     * @param string $status
     * @param string|null $patronId
     * @param string|null $message
     * @param string|null $barcode
     * @param int|null $itemId
     * @param array|null $metadata
     * @return ActivityLog
     */
    public function log(
        string $action,
        string $status,
        ?string $patronId = null,
        ?string $message = null,
        ?string $barcode = null,
        ?int $itemId = null,
        ?array $metadata = null
    ): ActivityLog {
        return ActivityLog::create([
            'patron_id' => $patronId,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'barcode' => $barcode,
            'item_id' => $itemId,
            'metadata' => $metadata,
        ]);
    }
}

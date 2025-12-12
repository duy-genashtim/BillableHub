<?php

namespace App\Helpers;

use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Helper trait for matching TimeDoctor worklog tasks with local task records.
 *
 * Implements two-step task matching:
 * 1. Match by TimeDoctor task_id in user_list JSON field
 * 2. Fallback to match by task_name (and enrich user_list with TimeDoctor task_id)
 */
trait TaskMatchingHelper
{
    /**
     * Match a task for a TimeDoctor worklog entry.
     *
     * This method implements a two-step matching strategy:
     * 1. First, try to find a task by TimeDoctor task_id in the user_list JSON field
     * 2. If not found and task_name is provided, try to match by task_name
     * 3. If matched by name, update the task's user_list to include the TimeDoctor task_id
     *
     * @param string|null $timeDoctorTaskId The TimeDoctor task_id from API response
     * @param string|null $taskName The task_name from TimeDoctor API response
     * @param int $timeDoctorVersion The TimeDoctor API version (1 or 2)
     * @return int|null The local task ID if found, null otherwise
     */
    public static function matchTaskForWorklog(
        ?string $timeDoctorTaskId,
        ?string $taskName,
        int $timeDoctorVersion
    ): ?int {
        // Return null if both identifiers are missing
        if (empty($timeDoctorTaskId) && empty($taskName)) {
            return null;
        }

        // Step 1: Try to match by TimeDoctor task_id
        if (!empty($timeDoctorTaskId)) {
            $task = self::matchByTimeDoctorId($timeDoctorTaskId, $timeDoctorVersion);
            if ($task) {
                return $task->id;
            }
        }

        // Step 2: Fallback to match by task_name
        if (!empty($taskName)) {
            $task = self::matchByTaskName($taskName);
            if ($task) {
                // Enrich the task with TimeDoctor task_id if we have it
                if (!empty($timeDoctorTaskId)) {
                    self::enrichTaskWithTimeDoctorId($task, $timeDoctorTaskId, $timeDoctorVersion);
                }
                return $task->id;
            }
        }

        // No match found
        return null;
    }

    /**
     * Match a task by TimeDoctor task_id in the user_list JSON field.
     *
     * Uses multiple query strategies for compatibility:
     * - whereJsonContains for native JSON support
     * - LIKE queries for fallback compatibility
     *
     * @param string $timeDoctorTaskId The TimeDoctor task_id
     * @param int $timeDoctorVersion The TimeDoctor version (1 or 2)
     * @return Task|null
     */
    private static function matchByTimeDoctorId(string $timeDoctorTaskId, int $timeDoctorVersion): ?Task
    {
        return Task::where(function ($query) use ($timeDoctorTaskId, $timeDoctorVersion) {
            $query->whereJsonContains('user_list', ['tId' => $timeDoctorTaskId, 'vId' => $timeDoctorVersion])
                ->orWhere('user_list', 'like', '%"tId":"' . $timeDoctorTaskId . '"%')
                ->orWhere('user_list', 'like', '%"tId":' . $timeDoctorTaskId . '%');
        })->first();
    }

    /**
     * Match a task by task_name.
     *
     * If multiple tasks exist with the same name, returns the first match.
     *
     * @param string $taskName The task name from TimeDoctor API
     * @return Task|null
     */
    private static function matchByTaskName(string $taskName): ?Task
    {
        return Task::where('task_name', $taskName)->first();
    }

    /**
     * Enrich a task's user_list with TimeDoctor task_id mapping.
     *
     * This method:
     * 1. Adds the TimeDoctor task_id to the task's user_list JSON field
     * 2. Updates the last_synced_at timestamp
     * 3. Uses database transaction with row locking to prevent race conditions
     *
     * @param Task $task The task to enrich
     * @param string $timeDoctorTaskId The TimeDoctor task_id to add
     * @param int $timeDoctorVersion The TimeDoctor version (1 or 2)
     * @return void
     */
    private static function enrichTaskWithTimeDoctorId(
        Task $task,
        string $timeDoctorTaskId,
        int $timeDoctorVersion
    ): void {
        try {
            DB::transaction(function () use ($task, $timeDoctorTaskId, $timeDoctorVersion) {
                // Lock the task row to prevent concurrent updates
                $taskToUpdate = Task::where('id', $task->id)->lockForUpdate()->first();

                if (!$taskToUpdate) {
                    return;
                }

                // Get existing user_list or initialize as empty array
                $existingUserList = $taskToUpdate->user_list ?? [];
                if (!is_array($existingUserList)) {
                    $existingUserList = [];
                }

                // Create the new entry
                $newEntry = [
                    'tId' => $timeDoctorTaskId,
                    'vId' => $timeDoctorVersion,
                ];

                // Check if this exact entry already exists (defensive check)
                $exists = collect($existingUserList)->contains(function ($entry) use ($newEntry) {
                    return isset($entry['tId']) && $entry['tId'] === $newEntry['tId']
                        && isset($entry['vId']) && $entry['vId'] === $newEntry['vId'];
                });

                // Only update if the entry doesn't already exist
                if (!$exists) {
                    $existingUserList[] = $newEntry;
                    $taskToUpdate->user_list = $existingUserList;
                    $taskToUpdate->last_synced_at = now();
                    $taskToUpdate->save();

                    Log::info('Task enriched with TimeDoctor task_id', [
                        'task_id' => $taskToUpdate->id,
                        'task_name' => $taskToUpdate->task_name,
                        'timedoctor_task_id' => $timeDoctorTaskId,
                        'timedoctor_version' => $timeDoctorVersion,
                    ]);
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to enrich task with TimeDoctor task_id', [
                'task_id' => $task->id,
                'timedoctor_task_id' => $timeDoctorTaskId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

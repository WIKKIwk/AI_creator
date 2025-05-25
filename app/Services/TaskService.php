<?php

namespace App\Services;

use App\Models\Task;

class TaskService
{
    public static function createTask(
        array $toUserIds,
        $relatedType,
        $relatedId,
        $action,
        $comment = null
    ): void {
        Task::query()->create([
            'from_user_id' => auth()->user()->id,
            'to_user_ids' => $toUserIds,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'action' => $action,
            'comment' => $comment,
        ]);
    }

    public static function createTaskForRoles(
        array $toUserRoles,
        $relatedType,
        $relatedId,
        $action,
        $comment = null
    ): void {
        Task::query()->create([
            'from_user_id' => auth()->user()->id,
            'to_user_roles' => $toUserRoles,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'action' => $action,
            'comment' => $comment,
        ]);
    }
}

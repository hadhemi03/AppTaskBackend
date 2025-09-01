<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\UserNotification;
use App\Events\TestNotification;
use Carbon\Carbon;

class SendTaskNotifications extends Command
{
    // Correct signature used by Artisan and scheduler
    protected $signature = 'tasks:notify-due-tomorrow';
    protected $description = 'Send notifications for tasks due tomorrow';

    public function handle()
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        // eager-load user to avoid N+1
        $tasks = Task::with('user')->whereDate('due_date', $tomorrow)->get();

        $count = 0;

        foreach ($tasks as $task) {
            if (!$task->user) {
                continue;
            }

            // scheduledAt: one day before the due date at 09:00
            $scheduledAt = Carbon::parse($task->due_date)->subDay()->setTime(9, 0, 0);

            // skip if we already created a notification for this user+task on that scheduled day
            $exists = UserNotification::where('task_id', $task->id)
                ->where('user_id', $task->user->id)
                ->whereDate('scheduled_at', $scheduledAt->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            // create the notification record
            $notification = UserNotification::create([
                'user_id' => $task->user->id,
                'task_id' => $task->id,
                'title' => 'Task due tomorrow',
                'body' => "Task '{$task->title}' is due on {$task->due_date}",
                'data' => [
                    'task' => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'starred' => $task->starred,
                        'due_date' => $task->due_date,
                    ]
                ],

                'scheduled_at' => $scheduledAt,
                'sent_at' => now(),
                'is_read' => false

            ]);

            // send real-time notification 
            event(new TestNotification([
                'title' => $notification->title,
                'body' => $notification->body,
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'starred' => $task->starred,
                    'due_date' => $task->due_date,
                ],
                'task_id' => $task->id,
                'notification_id' => $notification->id,
            ], $task->user->id));

            $count++;
        }

        $this->info("Notifications sent: {$count}");

        // return success code
        return 0;
    }
}

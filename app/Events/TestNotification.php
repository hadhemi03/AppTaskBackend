<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestNotification implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $title;
    public $body;
    public $task;
    public $task_id;
    public $notification_id;

    protected $userId;

    public function __construct(array $message, $userId)
    {
        $this->title = $message['title'] ?? null;
        $this->body = $message['body'] ?? null;
        $this->task = $message['task'] ?? null;
        $this->task_id = $message['task_id'] ?? null;
        $this->notification_id = $message['notification_id'] ?? null;

        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'test-notification';
    }

    public function broadcastWith()
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'task' => $this->task,
            'task_id' => $this->task_id,
            'notification_id' => $this->notification_id,
        ];
    }
}

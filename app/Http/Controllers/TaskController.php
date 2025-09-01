<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\UserNotification;
use App\Events\TestNotification;

class TaskController extends Controller
{
    // Get All Tasks 
    public function index(Request $request)
    {
        if ($request->user()->role === 'admin') {
            // Admin sees all tasks with the user who created them
            $tasks = Task::with('user')->get()->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'created_by' => $task->user->name, // who created it
                    'due_date' => $task->due_date,
                    'starred' => $task->starred,
                ];
            });
        } else {
            // Regular users see only their own tasks
            $tasks = $request->user()->tasks()->get();
        }

        return response()->json($tasks);
    }

    // Create a new task
    public function store(Request $request)
    {

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tasks')->where(
                    fn($query) =>
                    $query->where('user_id', $request->user()->id)
                ),
            ],   //unique in the tasks table for the currently authenticated user
            'description' => 'required|string',
            'status' => 'required|in:new,to do,in progress,pending,done',
            'due_date' => 'required|date_format:Y-m-d H:i',
            'starred' => 'boolean'
        ]);

        // creates a new task for the current user
        $task = $request->user()->tasks()->create($validated);

        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $message = [
                'title' => 'New Task Added',
                'body' => "{$request->user()->name} added a new task: '{$task->title}'",
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'starred' => $task->starred,
                    'due_date' => $task->due_date,
                    'created_by' => $request->user()->name,
                ],
                'task_id' => $task->id,
            ];


            // store in DB
            UserNotification::create([
                'user_id' => $admin->id,
                'task_id' => $task->id,
                'title' => $message['title'],
                'body' => $message['body'],
                'data' => json_encode(['task' => $message['task']]), // optional
                'sent_at' => now(),
            ]);


            // broadcast real-time
            event(new TestNotification($message, $admin->id));
        }


        return response()->json($task, 201);  // 201 : created ( resource made )
    }

    // Show a task
    public function show(Task $task, Request $request)
    {
        if ($request->user()->role !== 'admin' && $task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($task);
    }


    // update a task
    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tasks')
                    ->ignore($task->id) // allow current task’s title
                    ->where(fn($query) => $query->where('user_id', $request->user()->id)),
            ],
            'description' => 'required|string',
            'status' => 'required|in:new,to do,in progress,pending,done',
            'due_date' => 'required|date_format:Y-m-d H:i',
            'starred' => 'boolean'
        ]);

        $task->update($validated);
        return response()->json($task);
    }

    // Delete a task
    public function destroy(Task $task)
    {
        $task->delete();
        return response()->json(['message' => 'Task Deleted']);
    }

    public function dueToday(Request $request)
    {
        $today = Carbon::today()->toDateString();

        if ($request->user()->role === 'admin') {
            $tasks = Task::with('user')
                ->whereDate('due_date', $today)
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'due_date' => $task->due_date,
                        'starred' => $task->starred,
                        'created_by' => $task->user->name,
                    ];
                });
        } else {
            $tasks = $request->user()->tasks()->whereDate('due_date', $today)->get();
        }
        return response()->json($tasks);
    }

    public function dueThisWeek(Request $request)
    {
        $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek()->toDateString();

        if ($request->user()->role === 'admin') {
            $tasks = Task::with('user')
                ->whereBetween('due_date', [$startOfWeek, $endOfWeek])
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'due_date' => $task->due_date,
                        'starred' => $task->starred,
                        'created_by' => $task->user->name,
                    ];
                });
        } else {
            $tasks = $request->user()->tasks()
                ->whereBetween('due_date', [$startOfWeek, $endOfWeek])
                ->get();
        }
        return response()->json($tasks);


    }

    // starred become true
    public function toggleStar(Task $task)
    {
        $task->starred = !$task->starred; // flip true/false
        $task->save();

        return response()->json($task);
    }

    // Get all starred tasks 
    public function starredTasks(Request $request)
    {
        if ($request->user()->role === 'admin') {
            $tasks = Task::with('user')
                ->where('starred', true)
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'due_date' => $task->due_date,
                        'starred' => $task->starred,
                        'created_by' => $task->user->name,
                    ];
                });
        } else {
            $tasks = $request->user()->tasks()->where('starred', true)->get();
        }

        return response()->json($tasks);
    }

}

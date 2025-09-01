<?php

use App\Events\TestNotification;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Models\Task;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/documentation', function () {
    return view('documentation');
});
// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {  // only authenticated users 
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::put('/update', [AuthController::class, 'update']);


    // CRUD
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/addtask', [TaskController::class, 'store']);
    Route::get('/gettask/{task}', [TaskController::class, 'show']);
    Route::put('/updatetask/{task}', [TaskController::class, 'update']);
    Route::delete('/deletetask/{task}', [TaskController::class, 'destroy']);
    Route::get('/tasks/due-today', [TaskController::class, 'dueToday']);
    Route::get('/tasks/due-this-week', [TaskController::class, 'dueThisWeek']);
    Route::patch('/tasks/{task}/star', [TaskController::class, 'toggleStar']);
    Route::get('/tasks/starred', [TaskController::class, 'starredTasks']);

    Route::post('/test-notification', function (Request $request) {
        $userId = $request->input('user_id');
        $taskId = $request->input('task_id');

        // Retrieve the task
        $task = Task::find($taskId);

        if (!$task) {
            return response()->json(['status' => 'Task not found'], 404);
        }

        // Prepare the message
        $message = [
            'title' => 'Task Reminder',
            'body' => "Your task '{$task->title}' is due tomorrow ({$task->due_date}).",
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'starred' => $task->starred,
                'due_date' => $task->due_date,
                'created_by' => $task->user->name ?? null,
            ],
            'task_id' => $task->id,
        ];

        $notif = UserNotification::create([
            'user_id' => $userId,
            'task_id' => $taskId,
            'title' => $message['title'],
            'body' => $message['body'],
            'data' => ['task' => $message['task']],
            'sent_at' => now(),
        ]);

        // Trigger the event with the DB ID included
        event(new TestNotification([
            'id' => $notif->id,           // ✅ include DB ID
            'title' => $notif->title,
            'body' => $notif->body,
            'task' => $message['task']
        ], $userId));


        return response()->json([
            'status' => 'Notification sent!',
            'id' => $notif->id,
            'message' => $message
        ]);

    });

});

// only for test 
Route::middleware('auth:sanctum')->post('/dev/run-notify-due-tomorrow', function () {
    Artisan::call('tasks:notify-due-tomorrow');
    return response()->json(['ran_at' => now()]);
});


Route::middleware('auth:sanctum')->get('/notifications', function (Request $request) {
    return response()->json(
        UserNotification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
    );
});



Route::middleware('auth:sanctum')->patch('/notifications/{id}/read', function ($id, Request $request) {
    $notif = UserNotification::where('user_id', $request->user()->id)
        ->where('id', $id)
        ->firstOrFail();

    $notif->is_read = true;
    $notif->save();

    return response()->json(['status' => 'ok']);
});











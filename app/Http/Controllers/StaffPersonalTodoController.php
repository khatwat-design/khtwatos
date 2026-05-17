<?php

namespace App\Http\Controllers;

use App\Models\StaffPersonalTodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffPersonalTodoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
        ]);

        $userId = (int) $request->user()->id;
        $maxOrder = (int) StaffPersonalTodo::query()
            ->where('user_id', $userId)
            ->where('is_done', false)
            ->max('sort_order');

        StaffPersonalTodo::query()->create([
            'user_id' => $userId,
            'title' => trim($data['title']),
            'is_done' => false,
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('home.index')
            ->with('success', 'تمت إضافة المهمة إلى قائمتك.');
    }

    public function update(Request $request, StaffPersonalTodo $staffPersonalTodo): RedirectResponse
    {
        $this->authorizeTodo($request, $staffPersonalTodo);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('title', $data)) {
            $staffPersonalTodo->title = trim($data['title']);
        }

        if (array_key_exists('is_done', $data)) {
            $isDone = (bool) $data['is_done'];
            $staffPersonalTodo->is_done = $isDone;
            $staffPersonalTodo->completed_at = $isDone ? now() : null;
            if ($isDone) {
                $staffPersonalTodo->sort_order = 0;
            } else {
                $maxOrder = (int) StaffPersonalTodo::query()
                    ->where('user_id', $staffPersonalTodo->user_id)
                    ->where('is_done', false)
                    ->max('sort_order');
                $staffPersonalTodo->sort_order = $maxOrder + 1;
            }
        }

        $staffPersonalTodo->save();

        return redirect()
            ->route('home.index')
            ->with('success', $staffPersonalTodo->is_done ? 'تم إنجاز المهمة.' : 'تمت إعادة المهمة للقائمة.');
    }

    public function destroy(Request $request, StaffPersonalTodo $staffPersonalTodo): RedirectResponse
    {
        $this->authorizeTodo($request, $staffPersonalTodo);
        $staffPersonalTodo->delete();

        return redirect()
            ->route('home.index')
            ->with('success', 'تم حذف المهمة.');
    }

    private function authorizeTodo(Request $request, StaffPersonalTodo $todo): void
    {
        if ((int) $todo->user_id !== (int) $request->user()?->id) {
            abort(403);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Fsp\AnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function show(Request $request, AnalyticsService $analytics): Response
    {
        /** @var User $user */
        $user = $request->user();
        return Inertia::render('FspWorkspace', ['workspace' => $analytics->for($user)]);
    }

    public function comment(Request $request, int $case): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $caseRow = $this->authorizedCase($user, $case);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'internal' => ['sometimes', 'boolean']]);
        $internal = $user->role === 'applicant' ? false : (bool) ($data['internal'] ?? false);

        DB::transaction(function () use ($caseRow, $user, $data, $internal) {
            DB::table('case_comments')->insert(['case_id' => $caseRow->case_id, 'user_id' => $user->getKey(), 'body' => $data['body'], 'is_internal' => $internal, 'created_at' => now()]);
            DB::table('case_events')->insert([
                'case_id' => $caseRow->case_id, 'actor_user_id' => $user->getKey(), 'event_type' => 'comment',
                'title' => $internal ? 'Добавлен внутренний комментарий' : 'Добавлен комментарий',
                'details' => mb_substr($data['body'], 0, 240), 'metadata' => json_encode(['internal' => $internal], JSON_UNESCAPED_UNICODE), 'created_at' => now(),
            ]);
            DB::table('interaction_cases')->where('case_id', $caseRow->case_id)->update(['last_interaction_at' => now()]);
        });
        return back()->with('success', 'Комментарий добавлен.');
    }

    public function addTask(Request $request, int $case): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $caseRow = $this->authorizedCase($user, $case, requireManager: true);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:3000'],
            'skill_node_id' => ['nullable', 'integer', 'exists:skill_nodes,skill_node_id'], 'due_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($caseRow, $user, $data) {
            $taskId = DB::table('case_tasks')->insertGetId([
                'case_id' => $caseRow->case_id, 'title' => $data['title'], 'description' => $data['description'] ?? null,
                'skill_node_id' => $data['skill_node_id'] ?? null, 'status' => 'open', 'due_at' => $data['due_at'] ?? null,
                'created_by' => $user->getKey(), 'created_at' => now(),
            ]);
            DB::table('case_events')->insert([
                'case_id' => $caseRow->case_id, 'actor_user_id' => $user->getKey(), 'event_type' => 'task_created',
                'title' => 'Создана адресная задача', 'details' => $data['title'], 'metadata' => json_encode(['task_id' => $taskId], JSON_UNESCAPED_UNICODE), 'created_at' => now(),
            ]);
            DB::table('interaction_cases')->where('case_id', $caseRow->case_id)->update([
                'status' => 'task', 'progress' => DB::raw('GREATEST(progress,45)'), 'last_interaction_at' => now(),
                'next_action_at' => $data['due_at'] ?? now()->addDays(3),
            ]);
        });
        return back()->with('success', 'Задача добавлена в процесс.');
    }

    public function updateTask(Request $request, int $case, int $task): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $caseRow = $this->authorizedCase($user, $case);
        $taskRow = DB::table('case_tasks')->where('case_id', $caseRow->case_id)->where('task_id', $task)->first();
        abort_unless($taskRow, 404);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'done', 'cancelled'])], 'result_score' => ['nullable', 'numeric', 'min:0', 'max:100']]);
        if ($user->role === 'applicant' && ! in_array($data['status'], ['in_progress', 'done'], true)) abort(403);

        DB::transaction(function () use ($caseRow, $taskRow, $user, $data) {
            DB::table('case_tasks')->where('task_id', $taskRow->task_id)->update([
                'status' => $data['status'],
                'result_score' => $user->role === 'applicant' ? $taskRow->result_score : ($data['result_score'] ?? $taskRow->result_score),
                'completed_at' => $data['status'] === 'done' ? now() : null,
            ]);
            DB::table('case_events')->insert([
                'case_id' => $caseRow->case_id, 'actor_user_id' => $user->getKey(), 'event_type' => 'task_status',
                'title' => 'Изменён статус задачи', 'details' => $taskRow->title.' → '.$data['status'],
                'metadata' => json_encode(['task_id' => $taskRow->task_id], JSON_UNESCAPED_UNICODE), 'created_at' => now(),
            ]);
            DB::table('interaction_cases')->where('case_id', $caseRow->case_id)->update(['last_interaction_at' => now()]);
        });
        return back()->with('success', 'Статус задачи обновлён.');
    }

    public function respondInvitation(Request $request, int $case): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);
        $caseRow = $this->authorizedCase($user, $case);
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])]]);
        abort_unless($caseRow->source === 'match_invite' && $caseRow->status === 'new', 422);

        DB::transaction(function () use ($caseRow, $user, $data) {
            $accepted = $data['decision'] === 'accept';
            DB::table('interaction_cases')->where('case_id', $caseRow->case_id)->update([
                'status' => $accepted ? 'screening' : 'declined', 'progress' => $accepted ? 20 : 0,
                'contacts_unlocked_at' => $accepted ? now() : null, 'last_interaction_at' => now(),
                'next_action_at' => $accepted ? now()->addDays(2) : null,
            ]);
            DB::table('case_events')->insert([
                'case_id' => $caseRow->case_id, 'actor_user_id' => $user->getKey(), 'event_type' => $accepted ? 'accepted' : 'declined',
                'title' => $accepted ? 'Кандидат принял приглашение' : 'Кандидат отклонил приглашение',
                'details' => $accepted ? 'После взаимного шага контакты сторон разблокированы.' : 'Процесс сохранён в истории и может быть переоткрыт позднее.',
                'metadata' => json_encode(['decision' => $data['decision']], JSON_UNESCAPED_UNICODE), 'created_at' => now(),
            ]);
        });
        return back()->with('success', $data['decision'] === 'accept' ? 'Приглашение принято.' : 'Приглашение отклонено.');
    }

    public function updateStatus(Request $request, int $case): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $caseRow = $this->authorizedCase($user, $case, requireManager: true);
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'screening', 'task', 'interview', 'offer', 'hired', 'rejected', 'declined', 'paused', 'reopened', 'archived'])],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'], 'next_action_at' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($caseRow, $user, $data) {
            DB::table('interaction_cases')->where('case_id', $caseRow->case_id)->update([
                'status' => $data['status'], 'progress' => $data['progress'] ?? $this->progressForStatus($data['status']),
                'last_interaction_at' => now(), 'next_action_at' => $data['next_action_at'] ?? null,
            ]);
            DB::table('case_events')->insert([
                'case_id' => $caseRow->case_id, 'actor_user_id' => $user->getKey(), 'event_type' => 'status_changed',
                'title' => 'Изменён этап процесса', 'details' => ($caseRow->status ?? 'new').' → '.$data['status'].(! empty($data['note']) ? ': '.$data['note'] : ''),
                'metadata' => json_encode(['from' => $caseRow->status, 'to' => $data['status']], JSON_UNESCAPED_UNICODE), 'created_at' => now(),
            ]);
        });
        return back()->with('success', 'Этап процесса обновлён.');
    }

    private function authorizedCase(User $user, int $caseId, bool $requireManager = false): object
    {
        $case = DB::table('interaction_cases')->where('case_id', $caseId)->first();
        abort_unless($case, 404);
        if ($user->role === 'admin') return $case;
        if ($requireManager && $user->role !== 'employer') abort(403);
        if ($user->role === 'applicant') {
            abort_unless((int) $case->candidate_id === (int) $user->getKey(), 403);
            return $case;
        }
        if ($user->role === 'employer') {
            $allowed = DB::table('company_members')->where('user_id', $user->getKey())->where('company_id', $case->company_id)->where('active', true)->exists();
            abort_unless($allowed, 403);
            return $case;
        }
        abort(403);
    }

    private function progressForStatus(string $status): int
    {
        return match ($status) {
            'new' => 10, 'screening' => 25, 'task' => 45, 'interview' => 65, 'offer' => 85, 'hired' => 100,
            'reopened' => 35, 'paused', 'rejected', 'declined', 'archived' => 0, default => 0,
        };
    }
}

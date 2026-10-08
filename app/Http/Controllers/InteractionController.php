<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InteractionController extends Controller
{
    public function apply(Request $request, int $job): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1500']]);
        $message = trim((string) ($data['message'] ?? ''));

        $jobRow = DB::table('jobs')->where('job_id', $job)->where('status', 'open')->first();
        abort_unless($jobRow, 404);

        $existing = DB::table('interaction_cases')
            ->where('candidate_id', $user->getKey())
            ->where('job_id', $jobRow->job_id)
            ->orderByDesc('case_id')
            ->first();

        if ($existing && ! in_array($existing->status, ['rejected', 'declined', 'archived'], true)) {
            return back()->with('info', 'Отклик уже находится в процессе.');
        }

        DB::transaction(function () use ($user, $jobRow, $existing, $message) {
            $caseId = DB::table('interaction_cases')->insertGetId([
                'candidate_id' => $user->getKey(),
                'company_id' => $jobRow->company_id,
                'job_id' => $jobRow->job_id,
                'owner_user_id' => $jobRow->employer_id,
                'status' => $existing ? 'reopened' : 'new',
                'progress' => $existing ? 15 : 10,
                'priority' => 'normal',
                'source' => $existing ? 'reopened' : 'self_application',
                'reopened_from_case_id' => $existing?->case_id,
                'last_interaction_at' => now(),
                'next_action_at' => now()->addDays(2),
                'created_at' => now(),
            ]);

            DB::table('case_participants')->insertOrIgnore([
                ['case_id' => $caseId, 'user_id' => $user->getKey(), 'role_in_case' => 'candidate', 'can_decide' => false],
                ['case_id' => $caseId, 'user_id' => $jobRow->employer_id, 'role_in_case' => 'employer_owner', 'can_decide' => true],
            ]);

            DB::table('case_events')->insert([
                'case_id' => $caseId,
                'actor_user_id' => $user->getKey(),
                'event_type' => $existing ? 'application_reopened' : 'self_application',
                'title' => $existing ? 'Кандидат повторно открыл интерес к вакансии' : 'Кандидат откликнулся на вакансию',
                'details' => $message !== '' ? $message : $jobRow->title,
                'metadata' => json_encode(['job_id' => $jobRow->job_id, 'has_message' => $message !== ''], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);

            if ($message !== '') {
                DB::table('case_comments')->insert([
                    'case_id' => $caseId,
                    'user_id' => $user->getKey(),
                    'body' => '[Сопроводительное сообщение] '.$message,
                    'is_internal' => false,
                    'created_at' => now(),
                ]);
            }

            DB::table('interaction_alerts')->insert([
                'user_id' => $jobRow->employer_id,
                'case_id' => $caseId,
                'type' => 'new_application',
                'severity' => 'info',
                'title' => 'Новый отклик: '.$user->username.' → '.$jobRow->title,
                'is_read' => false,
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Отклик отправлен работодателю.');
    }

    public function invite(Request $request, int $job, int $candidate): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'employer', 403);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1500']]);
        $message = trim((string) ($data['message'] ?? ''));

        $jobRow = DB::table('jobs')->where('job_id', $job)->where('status', 'open')->first();
        abort_unless($jobRow, 404);

        $allowed = (int) $jobRow->employer_id === (int) $user->getKey()
            || DB::table('company_members')
                ->where('user_id', $user->getKey())
                ->where('company_id', $jobRow->company_id)
                ->where('active', true)
                ->exists();
        abort_unless($allowed, 403);

        $candidateRow = DB::table('users')->where('user_id', $candidate)->where('role', 'applicant')->first();
        abort_unless($candidateRow, 404);

        $existing = DB::table('interaction_cases')
            ->where('candidate_id', $candidateRow->user_id)
            ->where('job_id', $jobRow->job_id)
            ->orderByDesc('case_id')
            ->first();

        if ($existing && ! in_array($existing->status, ['rejected', 'declined', 'archived'], true)) {
            return back()->with('info', 'С этим кандидатом уже есть активный процесс по вакансии.');
        }

        DB::transaction(function () use ($user, $jobRow, $candidateRow, $existing, $message) {
            $caseId = DB::table('interaction_cases')->insertGetId([
                'candidate_id' => $candidateRow->user_id,
                'company_id' => $jobRow->company_id,
                'job_id' => $jobRow->job_id,
                'owner_user_id' => $user->getKey(),
                'status' => 'new',
                'progress' => 10,
                'priority' => 'normal',
                'source' => 'match_invite',
                'reopened_from_case_id' => $existing?->case_id,
                'last_interaction_at' => now(),
                'next_action_at' => now()->addDays(3),
                'created_at' => now(),
            ]);

            DB::table('case_participants')->insertOrIgnore([
                ['case_id' => $caseId, 'user_id' => $candidateRow->user_id, 'role_in_case' => 'candidate', 'can_decide' => false],
                ['case_id' => $caseId, 'user_id' => $user->getKey(), 'role_in_case' => 'employer_owner', 'can_decide' => true],
            ]);

            DB::table('case_events')->insert([
                'case_id' => $caseId,
                'actor_user_id' => $user->getKey(),
                'event_type' => 'match_invite',
                'title' => 'Работодатель отправил адресное приглашение',
                'details' => $message !== '' ? $message : $jobRow->title,
                'metadata' => json_encode(['job_id' => $jobRow->job_id, 'has_message' => $message !== ''], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);

            if ($message !== '') {
                DB::table('case_comments')->insert([
                    'case_id' => $caseId,
                    'user_id' => $user->getKey(),
                    'body' => '[Сообщение к приглашению] '.$message,
                    'is_internal' => false,
                    'created_at' => now(),
                ]);
            }

            DB::table('interaction_alerts')->insert([
                'user_id' => $candidateRow->user_id,
                'case_id' => $caseId,
                'type' => 'new_invitation',
                'severity' => 'info',
                'title' => 'Новое приглашение: '.$jobRow->company.' · '.$jobRow->title,
                'is_read' => false,
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Адресное приглашение отправлено кандидату.');
    }
}

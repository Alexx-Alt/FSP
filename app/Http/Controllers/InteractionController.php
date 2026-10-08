<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $jobRow = $this->ownedOpenJob($user, $job);
        $candidateRow = $this->availableCandidate($candidate);
        abort_unless($candidateRow, 404);

        $created = $this->createInvitation($user, $jobRow, $candidateRow, $message, false);
        return $created
            ? back()->with('success', 'Адресное приглашение отправлено кандидату.')
            : back()->with('info', 'С этим кандидатом уже есть активный процесс по вакансии.');
    }

    public function inviteBulk(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'employer', 403);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.candidate_id' => ['required', 'integer', 'min:1'],
            'items.*.job_id' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:1500'],
        ]);
        $message = trim((string) ($data['message'] ?? ''));
        $created = 0;
        $skipped = 0;

        foreach (collect($data['items'])->unique(fn ($item) => $item['candidate_id'].'-'.$item['job_id']) as $item) {
            $jobRow = $this->ownedOpenJob($user, (int) $item['job_id']);
            $candidateRow = $this->availableCandidate((int) $item['candidate_id']);
            if (! $candidateRow) {
                $skipped++;
                continue;
            }
            $this->createInvitation($user, $jobRow, $candidateRow, $message, true) ? $created++ : $skipped++;
        }

        if ($created === 0) {
            return back()->with('info', 'Новые приглашения не созданы: профили недоступны или процессы уже активны.');
        }

        return back()->with('success', "Отправлено приглашений: {$created}. Пропущено: {$skipped}.");
    }

    private function ownedOpenJob(User $user, int $job): object
    {
        $jobRow = DB::table('jobs')->where('job_id', $job)->where('status', 'open')->first();
        abort_unless($jobRow, 404);
        $allowed = (int) $jobRow->employer_id === (int) $user->getKey()
            || DB::table('company_members')
                ->where('user_id', $user->getKey())
                ->where('company_id', $jobRow->company_id)
                ->where('active', true)
                ->exists();
        abort_unless($allowed, 403);
        return $jobRow;
    }

    private function availableCandidate(int $candidate): ?object
    {
        $query = DB::table('users')->where('user_id', $candidate)->where('role', 'applicant');
        if (Schema::hasColumn('users', 'profile_visibility')) $query->where('profile_visibility', 'employers');
        if (Schema::hasColumn('users', 'consent_processing')) $query->where('consent_processing', true);
        if (Schema::hasColumn('users', 'consent_publication')) $query->where('consent_publication', true);
        return $query->first();
    }

    private function createInvitation(User $user, object $jobRow, object $candidateRow, string $message, bool $bulk): bool
    {
        $existing = DB::table('interaction_cases')
            ->where('candidate_id', $candidateRow->user_id)
            ->where('job_id', $jobRow->job_id)
            ->orderByDesc('case_id')
            ->first();

        if ($existing && ! in_array($existing->status, ['rejected', 'declined', 'archived'], true)) {
            return false;
        }

        DB::transaction(function () use ($user, $jobRow, $candidateRow, $existing, $message, $bulk) {
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
                'event_type' => $bulk ? 'bulk_match_invite' : 'match_invite',
                'title' => $bulk ? 'Кандидат добавлен в адресную кампанию приглашений' : 'Работодатель отправил адресное приглашение',
                'details' => $message !== '' ? $message : $jobRow->title,
                'metadata' => json_encode(['job_id' => $jobRow->job_id, 'has_message' => $message !== '', 'bulk' => $bulk], JSON_UNESCAPED_UNICODE),
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

        return true;
    }
}

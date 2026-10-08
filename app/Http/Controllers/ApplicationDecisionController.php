<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationDecisionController extends Controller
{
    public function accept(Request $request, int $case): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'employer', 403);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1500']]);
        $message = trim((string) ($data['message'] ?? ''));

        $caseRow = DB::table('interaction_cases')->where('case_id', $case)->first();
        abort_unless($caseRow, 404);
        abort_unless(in_array($caseRow->source, ['self_application', 'reopened'], true), 422);
        abort_unless(in_array($caseRow->status, ['new', 'reopened'], true), 422);

        $allowed = DB::table('company_members')
            ->where('user_id', $user->getKey())
            ->where('company_id', $caseRow->company_id)
            ->where('active', true)
            ->exists();
        abort_unless($allowed, 403);

        DB::transaction(function () use ($caseRow, $user, $message) {
            DB::table('interaction_cases')->where('case_id', $caseRow->case_id)->update([
                'status' => 'screening',
                'progress' => 25,
                'contacts_unlocked_at' => now(),
                'last_interaction_at' => now(),
                'next_action_at' => now()->addDays(2),
            ]);

            DB::table('case_events')->insert([
                'case_id' => $caseRow->case_id,
                'actor_user_id' => $user->getKey(),
                'event_type' => 'application_accepted',
                'title' => 'Работодатель принял отклик',
                'details' => $message !== '' ? $message : 'После взаимного шага контакты сторон разблокированы.',
                'metadata' => json_encode(['contacts_unlocked' => true, 'has_message' => $message !== ''], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);

            if ($message !== '') {
                DB::table('case_comments')->insert([
                    'case_id' => $caseRow->case_id,
                    'user_id' => $user->getKey(),
                    'body' => '[Ответ на отклик] '.$message,
                    'is_internal' => false,
                    'created_at' => now(),
                ]);
            }

            DB::table('interaction_alerts')->insert([
                'user_id' => $caseRow->candidate_id,
                'case_id' => $caseRow->case_id,
                'type' => 'application_accepted',
                'severity' => 'info',
                'title' => 'Работодатель принял ваш отклик — контакты открыты',
                'is_read' => false,
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Отклик принят. Контакты разблокированы обеим сторонам.');
    }
}

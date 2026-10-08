<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProcessFeedbackController extends Controller
{
    public function store(Request $request, int $case): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $caseRow = DB::table('interaction_cases')->where('case_id', $case)->first();
        abort_unless($caseRow, 404);

        if ($user->role === 'applicant') {
            abort_unless((int) $caseRow->candidate_id === (int) $user->getKey(), 403);
        } elseif ($user->role === 'employer') {
            $allowed = DB::table('company_members')
                ->where('user_id', $user->getKey())
                ->where('company_id', $caseRow->company_id)
                ->where('active', true)
                ->exists();
            abort_unless($allowed, 403);
        } else {
            abort_unless($user->role === 'admin', 403);
        }

        $data = $request->validate([
            'context' => ['required', Rule::in(['application', 'hire', 'process'])],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
            'recommend' => ['nullable', 'boolean'],
        ]);

        if ($data['context'] === 'hire') {
            abort_unless($caseRow->status === 'hired', 422, 'Отзыв о найме доступен после завершения процесса.');
        }

        $actor = $user->role === 'applicant' ? 'Кандидат' : ($user->role === 'employer' ? 'Работодатель' : 'Администратор');
        $text = trim((string) ($data['body'] ?? ''));
        $details = $text !== '' ? $text : 'Оценка '.$data['rating'].' из 5';
        $metadata = [
            'context' => $data['context'],
            'rating' => (int) $data['rating'],
            'recommend' => array_key_exists('recommend', $data) ? (bool) $data['recommend'] : null,
            'actor_role' => $user->role,
        ];

        DB::transaction(function () use ($caseRow, $user, $actor, $details, $metadata, $text) {
            DB::table('case_events')->insert([
                'case_id' => $caseRow->case_id,
                'actor_user_id' => $user->getKey(),
                'event_type' => 'feedback',
                'title' => $actor.' оставил обратную связь',
                'details' => $details,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);

            if ($text !== '') {
                DB::table('case_comments')->insert([
                    'case_id' => $caseRow->case_id,
                    'user_id' => $user->getKey(),
                    'body' => '[Обратная связь '.$metadata['rating'].'/5] '.$text,
                    'is_internal' => false,
                    'created_at' => now(),
                ]);
            }

            DB::table('interaction_cases')->where('case_id', $caseRow->case_id)->update([
                'last_interaction_at' => now(),
            ]);
        });

        return back()->with('success', 'Обратная связь сохранена в истории процесса.');
    }
}

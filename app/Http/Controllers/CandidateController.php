<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Fsp\AnalyticsService;
use App\Services\Fsp\AssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CandidateController extends Controller
{
    private const CATEGORIES = ['Backend', 'Frontend', 'Data', 'ML/AI', 'DevOps', 'Full-stack'];

    public function show(Request $request, AnalyticsService $analytics, AssessmentService $assessment): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);

        return Inertia::render('CandidateCenter', [
            'workspace' => $analytics->for($user),
            'assessment' => $assessment->payload($user),
            'careerHistory' => Schema::hasTable('career_compass_runs')
                ? DB::table('career_compass_runs')->where('candidate_id', $user->getKey())->orderByDesc('created_at')->limit(5)->get()->map(fn ($row) => [
                    'category' => $row->recommended_category,
                    'confidence' => (int) $row->confidence,
                    'createdAt' => $row->created_at,
                ])->all()
                : [],
        ]);
    }

    public function saveCareer(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);

        $data = $request->validate([
            'recommended_category' => ['required', Rule::in(self::CATEGORIES)],
            'confidence' => ['required', 'integer', 'min:0', 'max:100'],
            'answers' => ['required', 'array', 'min:3', 'max:20'],
        ]);

        if (! Schema::hasTable('career_compass_runs') && ! in_array($data['recommended_category'], ['Backend', 'Frontend'], true)) {
            return back()->withErrors(['career' => 'Сначала выполните database/fsp_match_v6/04_phase1_candidate_center.sql, чтобы включить новые направления.']);
        }

        DB::transaction(function () use ($user, $data) {
            if (Schema::hasTable('career_compass_runs')) {
                DB::table('career_compass_runs')->insert([
                    'candidate_id' => $user->getKey(),
                    'recommended_category' => $data['recommended_category'],
                    'confidence' => $data['confidence'],
                    'answers_json' => json_encode($data['answers'], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ]);
            }

            // If the extended enum is not installed yet MySQL will reject unsupported tracks.
            // Backend/Frontend continue to work on the base schema.
            DB::table('users')->where('user_id', $user->getKey())->update([
                'primary_category' => $data['recommended_category'],
            ]);
        });

        return back()->with('success', 'Рекомендация Career Compass сохранена в профиле.');
    }

    public function submitAssessment(Request $request, AssessmentService $assessment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);

        $data = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'desired_grade' => ['required', Rule::in(['intern', 'junior', 'middle', 'senior', 'lead'])],
            'answers' => ['required', 'array', 'min:5', 'max:12'],
            'answers.*.question_id' => ['required'],
            'answers.*.selected_index' => ['required', 'integer', 'min:0', 'max:10'],
            'answers.*.response_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'integrity_events' => ['sometimes', 'array', 'max:100'],
            'integrity_events.*.type' => ['required_with:integrity_events', 'string', 'max:80'],
            'integrity_events.*.weight' => ['nullable', 'integer', 'min:1', 'max:10'],
            'integrity_events.*.metadata' => ['nullable', 'array'],
        ]);

        $result = $assessment->submit(
            $user,
            $data['category'],
            $data['desired_grade'],
            $data['answers'],
            $data['integrity_events'] ?? [],
        );

        $message = $result['passed']
            ? ($result['gradeChanged']
                ? "Assessment пройден: {$result['score']}%. Грейд подтверждён."
                : "Assessment пройден: {$result['score']}%. Результат сохранён, смена грейда пока на cooldown.")
            : "Assessment завершён: {$result['score']}%. Заявленный грейд пока не подтверждён.";

        return back()->with('success', $message);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);

        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'skills' => ['nullable', 'string', 'max:600'],
            'salary_expected' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'work_format' => ['nullable', Rule::in(['office', 'hybrid', 'remote', 'any'])],
            'desired_grade' => ['nullable', Rule::in(['intern', 'junior', 'middle', 'senior', 'lead'])],
        ]);

        $user->update($data);

        return back()->with('success', 'Профессиональный паспорт обновлён.');
    }
}

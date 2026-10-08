<?php

namespace App\Services\Fsp;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LiveAssessmentService
{
    private const GRADES = ['intern', 'junior', 'middle', 'senior', 'lead'];
    private const CATEGORIES = ['Backend', 'Frontend', 'Data', 'ML/AI', 'DevOps', 'Full-stack'];

    /** @return array<string,mixed> */
    public function start(User $user): array
    {
        abort_unless($user->role === 'applicant', 403);
        abort_unless(
            Schema::hasTable('assessment_live_sessions') && Schema::hasTable('assessment_code_questions'),
            503,
            'Сначала выполните database/fsp_match_v6/05_phase1_2_live_assessment.sql'
        );

        $category = in_array($user->primary_category, self::CATEGORIES, true) ? $user->primary_category : 'Backend';
        $grade = in_array($user->desired_grade, self::GRADES, true) ? $user->desired_grade : 'middle';
        $preferredDifficulty = (array_search($grade, self::GRADES, true) ?: 2) + 1;

        $choices = DB::table('assessment_questions')
            ->where('category', $category)
            ->where('active', true)
            ->orderByRaw('ABS(difficulty - ?) ASC', [$preferredDifficulty])
            ->orderByRaw('RAND()')
            ->limit(5)
            ->get();

        abort_if($choices->count() < 5, 503, 'В банке недостаточно вопросов для выбранной категории.');

        $code = DB::table('assessment_code_questions')
            ->where('category', $category)
            ->where('active', true)
            ->orderByRaw('ABS(difficulty - ?) ASC', [$preferredDifficulty])
            ->inRandomOrder()
            ->first();

        abort_unless($code, 503, 'Для категории нет практической задачи.');

        $variant = 'live-'.Str::lower(Str::random(8)).'-'.now()->format('YmdHis');
        $sessionId = DB::table('assessment_live_sessions')->insertGetId([
            'candidate_id' => $user->getKey(),
            'category' => $category,
            'desired_grade' => $grade,
            'status' => 'created',
            'variant_key' => $variant,
            'created_at' => now(),
        ]);

        $questions = $choices->map(fn ($q) => [
            'kind' => 'choice',
            'id' => (int) $q->question_id,
            'skill' => $q->skill_code,
            'prompt' => $q->prompt,
            'options' => json_decode($q->options_json, true) ?: [],
            'difficulty' => (int) $q->difficulty,
        ])->values()->all();

        $insertAt = min(count($questions), max(1, random_int(2, 4)));
        array_splice($questions, $insertAt, 0, [[
            'kind' => 'code',
            'id' => (int) $code->code_question_id,
            'skill' => $code->skill_code,
            'title' => $code->title,
            'prompt' => $code->prompt,
            'language' => $code->language,
            'starterCode' => $code->starter_code,
            'difficulty' => (int) $code->difficulty,
        ]]);

        return [
            'sessionId' => $sessionId,
            'category' => $category,
            'desiredGrade' => $grade,
            'variant' => $variant,
            'durationSeconds' => 15 * 60,
            'questions' => $questions,
            'rules' => [
                'Вопросы выдаются по одному.',
                'Смена вкладки, потеря фокуса, copy/paste и выход из полноэкранного режима фиксируются как сигналы.',
                'Сигнал сам по себе не означает нарушение: спорные сессии отправляются на проверку.',
                'Практическая задача оценивается отдельно и проверяется на подозрительное совпадение с прошлыми ответами.',
            ],
        ];
    }

    /** @param array<string,mixed> $metadata */
    public function recordEvent(User $user, int $sessionId, string $type, int $weight, array $metadata = []): void
    {
        $session = $this->session($user, $sessionId);
        abort_if($session->status === 'completed', 422, 'Сессия уже завершена.');

        DB::table('assessment_live_events')->insert([
            'live_session_id' => $sessionId,
            'candidate_id' => $user->getKey(),
            'event_type' => mb_substr($type, 0, 80),
            'weight' => min(10, max(1, $weight)),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        $risk = (int) DB::table('assessment_live_events')->where('live_session_id', $sessionId)->sum('weight');
        DB::table('assessment_live_sessions')->where('live_session_id', $sessionId)->update([
            'status' => $session->status === 'created' ? 'active' : $session->status,
            'started_at' => $session->started_at ?: now(),
            'risk_score' => min(100, $risk),
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $answers
     * @return array<string,mixed>
     */
    public function finish(User $user, int $sessionId, array $answers): array
    {
        $session = $this->session($user, $sessionId);
        abort_if($session->status === 'completed', 422, 'Сессия уже завершена.');

        $choiceAnswers = collect($answers)->where('kind', 'choice')->values();
        $codeAnswer = collect($answers)->firstWhere('kind', 'code');
        abort_if($choiceAnswers->count() < 4 || ! $codeAnswer, 422, 'Нужно ответить минимум на четыре вопроса и практическую задачу.');

        $choiceBank = DB::table('assessment_questions')
            ->whereIn('question_id', $choiceAnswers->pluck('question_id')->map(fn ($v) => (int) $v))
            ->get()->keyBy('question_id');

        $weightedCorrect = 0.0;
        $weightedTotal = 0.0;
        $storedChoiceAnswers = [];
        $skills = [];

        foreach ($choiceAnswers as $answer) {
            $q = $choiceBank->get((int) ($answer['question_id'] ?? 0));
            if (! $q) continue;
            $selected = isset($answer['selected_index']) ? (int) $answer['selected_index'] : -1;
            $correct = $selected === (int) $q->correct_index;
            $weight = 1 + (((int) $q->difficulty - 1) * 0.18);
            $weightedTotal += $weight;
            $weightedCorrect += $correct ? $weight : 0;
            $storedChoiceAnswers[] = [
                'question_id' => (int) $q->question_id,
                'selected_index' => max(0, $selected),
                'is_correct' => $correct,
                'response_ms' => min(900000, max(0, (int) ($answer['response_ms'] ?? 0))),
            ];
            $skills[] = ['skill' => $q->skill_code, 'correct' => $correct, 'difficulty' => (int) $q->difficulty];
        }

        $choiceScore = $weightedTotal > 0 ? ($weightedCorrect / $weightedTotal) * 100 : 0;

        $codeQuestion = DB::table('assessment_code_questions')
            ->where('code_question_id', (int) ($codeAnswer['question_id'] ?? 0))
            ->where('category', $session->category)
            ->first();
        abort_unless($codeQuestion, 422, 'Практическая задача не найдена.');

        $code = trim((string) ($codeAnswer['code'] ?? ''));
        $codeScore = $this->codeScore($codeQuestion->validator_json, $code);
        $similarity = $this->maxSimilarity((int) $codeQuestion->code_question_id, $code);
        if ($similarity >= 88) {
            $this->recordEvent($user, $sessionId, 'code_similarity', 10, ['similarity' => $similarity]);
        }

        $finalScore = round(($choiceScore * 0.78) + ($codeScore * 0.22), 1);
        $risk = min(100, (int) DB::table('assessment_live_events')->where('live_session_id', $sessionId)->sum('weight'));
        $integrityScore = max(50, 100 - min(50, $risk));
        $reviewRequired = $risk > 20 || $similarity >= 88;
        $threshold = $this->thresholdFor($session->desired_grade);
        $passed = $finalScore >= $threshold;

        $latest = DB::table('assessment_runs')->where('candidate_id', $user->getKey())->orderByDesc('completed_at')->first();
        $cooldownUntil = $latest?->completed_at ? Carbon::parse($latest->completed_at)->addDays(30) : null;
        $locked = $cooldownUntil?->isFuture() ?? false;

        $assessmentId = DB::transaction(function () use ($user, $session, $storedChoiceAnswers, $codeQuestion, $code, $codeScore, $similarity, $codeAnswer, $finalScore, $integrityScore, $latest, $passed, $reviewRequired, $locked, $skills) {
            $assessmentId = DB::table('assessment_runs')->insertGetId([
                'candidate_id' => $user->getKey(),
                'category' => $session->category,
                'desired_grade' => $session->desired_grade,
                'result_grade' => $session->desired_grade,
                'score' => $finalScore,
                'integrity_score' => $integrityScore,
                'variant_key' => $session->variant_key,
                'started_at' => $session->started_at ?: now()->subMinutes(10),
                'completed_at' => now(),
                'retest_of' => $latest?->assessment_id,
            ]);

            if ($storedChoiceAnswers) {
                DB::table('assessment_answers')->insert(array_map(fn ($row) => [
                    'assessment_id' => $assessmentId,
                    'question_id' => $row['question_id'],
                    'selected_index' => $row['selected_index'],
                    'is_correct' => $row['is_correct'],
                    'response_ms' => $row['response_ms'],
                    'created_at' => now(),
                ], $storedChoiceAnswers));
            }

            $normalized = $this->normalizeCode($code);
            DB::table('assessment_code_answers')->insert([
                'assessment_id' => $assessmentId,
                'code_question_id' => $codeQuestion->code_question_id,
                'candidate_id' => $user->getKey(),
                'answer_text' => $code,
                'normalized_hash' => hash('sha256', $normalized),
                'score' => $codeScore,
                'similarity_score' => $similarity,
                'response_ms' => min(900000, max(0, (int) ($codeAnswer['response_ms'] ?? 0))),
                'created_at' => now(),
            ]);

            $events = DB::table('assessment_live_events')->where('live_session_id', $session->live_session_id)->get();
            if ($events->isNotEmpty()) {
                DB::table('assessment_integrity_events')->insert($events->map(fn ($event) => [
                    'candidate_id' => $user->getKey(),
                    'assessment_id' => $assessmentId,
                    'event_type' => $event->event_type,
                    'weight' => $event->weight,
                    'metadata' => $event->metadata,
                    'created_at' => $event->created_at,
                ])->all());
            }

            foreach ($skills as $skill) {
                $this->upsertEvidence($user, $session->category, (string) $skill['skill'], $skill['correct'] ? 'confirmed' : 'partial', $skill['correct'] ? 82 : 45, 'Live assessment');
            }
            $this->upsertEvidence($user, $session->category, (string) $codeQuestion->skill_code, $codeScore >= 70 ? 'confirmed' : 'partial', (int) round($codeScore), 'Практическая задача');

            $profile = ['primary_category' => $session->category, 'desired_grade' => $session->desired_grade];
            if (! $reviewRequired) {
                if ($passed && (! $locked || $user->confirmed_grade === $session->desired_grade || ! $user->confirmed_grade)) {
                    $profile['confirmed_grade'] = $session->desired_grade;
                    $profile['grade_status'] = 'confirmed';
                } elseif (! $passed && ! $user->confirmed_grade) {
                    $profile['grade_status'] = 'not_confirmed';
                }
            }
            DB::table('users')->where('user_id', $user->getKey())->update($profile);

            DB::table('assessment_live_sessions')->where('live_session_id', $session->live_session_id)->update([
                'status' => 'completed',
                'risk_score' => min(100, (int) DB::table('assessment_live_events')->where('live_session_id', $session->live_session_id)->sum('weight')),
                'completed_at' => now(),
            ]);

            return $assessmentId;
        });

        return [
            'assessmentId' => $assessmentId,
            'score' => $finalScore,
            'choiceScore' => round($choiceScore, 1),
            'codeScore' => round($codeScore, 1),
            'integrityScore' => $integrityScore,
            'risk' => $risk,
            'similarity' => $similarity,
            'reviewRequired' => $reviewRequired,
            'passed' => $passed,
            'threshold' => $threshold,
        ];
    }

    private function session(User $user, int $sessionId): object
    {
        $session = DB::table('assessment_live_sessions')->where('live_session_id', $sessionId)->first();
        abort_unless($session, 404);
        abort_unless((int) $session->candidate_id === (int) $user->getKey(), 403);
        return $session;
    }

    private function codeScore(string $validatorJson, string $code): float
    {
        $validator = json_decode($validatorJson, true) ?: [];
        $minChars = max(20, (int) ($validator['min_chars'] ?? 60));
        $groups = $validator['required_groups'] ?? [];
        $lower = mb_strtolower($code);
        $lengthScore = min(35, (mb_strlen($code) / $minChars) * 35);
        $hit = 0;
        foreach ($groups as $group) {
            foreach ((array) $group as $needle) {
                if (str_contains($lower, mb_strtolower((string) $needle))) { $hit++; break; }
            }
        }
        $groupScore = count($groups) ? ($hit / count($groups)) * 65 : 65;
        return min(100, round($lengthScore + $groupScore, 1));
    }

    private function maxSimilarity(int $questionId, string $code): float
    {
        $normalized = $this->normalizeCode($code);
        if (mb_strlen($normalized) < 30) return 0;
        $max = 0.0;
        foreach (DB::table('assessment_code_answers')->where('code_question_id', $questionId)->latest('code_answer_id')->limit(80)->pluck('answer_text') as $previous) {
            $a = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $b = preg_split('/\s+/', $this->normalizeCode((string) $previous), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $union = array_unique([...$a, ...$b]);
            $intersection = array_intersect(array_unique($a), array_unique($b));
            $score = count($union) ? (count($intersection) / count($union)) * 100 : 0;
            $max = max($max, $score);
        }
        return round($max, 1);
    }

    private function normalizeCode(string $code): string
    {
        $code = mb_strtolower($code);
        $code = preg_replace('/\/\/.*$/m', '', $code) ?? $code;
        $code = preg_replace('/#.*$/m', '', $code) ?? $code;
        $code = preg_replace('/\s+/', ' ', $code) ?? $code;
        return trim($code);
    }

    private function upsertEvidence(User $user, string $category, string $skill, string $state, int $score, string $source): void
    {
        $node = DB::table('skill_nodes')->where('category', $category)->where(function ($q) use ($skill) {
            $q->where('name', $skill)->orWhere('code', 'like', '%.'.$skill)->orWhere('code', 'like', '%'.$skill.'%');
        })->orderByDesc('parent_id')->first();
        if (! $node) return;
        $existing = DB::table('candidate_skill_evidence')->where('candidate_id', $user->getKey())->where('skill_node_id', $node->skill_node_id)->where('source_type', 'assessment')->first();
        $data = ['state' => $state, 'source_label' => $source, 'score' => $score, 'verified_at' => now()];
        if ($existing) DB::table('candidate_skill_evidence')->where('evidence_id', $existing->evidence_id)->update($data);
        else DB::table('candidate_skill_evidence')->insert(['candidate_id' => $user->getKey(), 'skill_node_id' => $node->skill_node_id, 'source_type' => 'assessment', 'created_at' => now(), ...$data]);
    }

    private function thresholdFor(string $grade): int
    {
        return match ($grade) {
            'intern' => 48,
            'junior' => 58,
            'middle' => 68,
            'senior' => 76,
            'lead' => 82,
            default => 68,
        };
    }
}

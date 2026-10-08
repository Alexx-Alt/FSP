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
            Schema::hasTable('assessment_questions'),
            422,
            'Банк вопросов не развёрнут. Выполните database/fsp_match_v6/04_phase1_candidate_center.sql.'
        );

        $category = in_array($user->primary_category, self::CATEGORIES, true) ? $user->primary_category : 'Backend';
        $grade = in_array($user->desired_grade, self::GRADES, true) ? $user->desired_grade : 'middle';
        $rank = array_search($grade, self::GRADES, true);
        $preferredDifficulty = ($rank === false ? 2 : $rank) + 1;

        $choices = DB::table('assessment_questions')
            ->where('category', $category)
            ->where('active', true)
            ->orderByRaw('ABS(CAST(difficulty AS SIGNED) - ?) ASC', [$preferredDifficulty])
            ->orderByRaw('RAND()')
            ->limit(5)
            ->get();

        abort_if(
            $choices->count() < 5,
            422,
            'Для выбранной категории пока недостаточно вопросов. Запустите Phase 1 seed банка заданий.'
        );

        $persistentLive = $this->liveSchemaReady();
        $code = $persistentLive
            ? DB::table('assessment_code_questions')
                ->where('category', $category)
                ->where('active', true)
                ->orderByRaw('ABS(CAST(difficulty AS SIGNED) - ?) ASC', [$preferredDifficulty])
                ->inRandomOrder()
                ->first()
            : null;

        // Если практический банк ещё не заполнен, используем встроенное задание и file-session.
        // Так submit() потом не пытается искать fallback-вопрос по несуществующему ID в БД.
        $usingFallbackCode = $code === null;
        $code ??= $this->fallbackCodeQuestion($category);
        $persistSession = $persistentLive && ! $usingFallbackCode;

        $variant = 'live-'.Str::lower(Str::random(8)).'-'.now()->format('YmdHis');
        if ($persistSession) {
            $sessionId = (int) DB::table('assessment_live_sessions')->insertGetId([
                'candidate_id' => $user->getKey(),
                'category' => $category,
                'desired_grade' => $grade,
                'status' => 'created',
                'variant_key' => $variant,
                'created_at' => now(),
            ]);
        } else {
            $sessionId = random_int(100000000, 999999999);
            session()->put($this->fallbackSessionKey($sessionId), [
                '_fallback' => true,
                'live_session_id' => $sessionId,
                'candidate_id' => (int) $user->getKey(),
                'category' => $category,
                'desired_grade' => $grade,
                'status' => 'created',
                'variant_key' => $variant,
                'risk_score' => 0,
                'started_at' => null,
                'completed_at' => null,
                'events' => [],
                'code_question' => (array) $code,
            ]);
        }

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
            'persistentIntegrity' => $persistSession,
            'rules' => [
                'Вопросы выдаются по одному.',
                'Смена вкладки, потеря фокуса, copy/paste и выход из полноэкранного режима фиксируются как сигналы.',
                'Сигнал сам по себе не означает нарушение: спорные сессии отправляются на проверку.',
                'Практическая задача оценивается отдельно и проверяется на подозрительное совпадение, когда Phase 1.2 schema доступна.',
            ],
        ];
    }

    /** @param array<string,mixed> $metadata */
    public function recordEvent(User $user, int $sessionId, string $type, int $weight, array $metadata = []): void
    {
        $session = $this->session($user, $sessionId);
        abort_if($session->status === 'completed', 422, 'Сессия уже завершена.');

        if (($session->_fallback ?? false) === true) {
            $stored = session()->get($this->fallbackSessionKey($sessionId), []);
            $stored['events'] ??= [];
            $stored['events'][] = [
                'event_type' => mb_substr($type, 0, 80),
                'weight' => min(10, max(1, $weight)),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'created_at' => now()->toDateTimeString(),
            ];
            $stored['risk_score'] = min(100, (int) collect($stored['events'])->sum('weight'));
            $stored['status'] = $stored['status'] === 'created' ? 'active' : $stored['status'];
            $stored['started_at'] ??= now()->toDateTimeString();
            session()->put($this->fallbackSessionKey($sessionId), $stored);
            return;
        }

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
            if (! $q) {
                continue;
            }
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

        if (($session->_fallback ?? false) === true) {
            $storedSession = session()->get($this->fallbackSessionKey($sessionId), []);
            $codeQuestion = (object) ($storedSession['code_question'] ?? []);
        } else {
            $codeQuestion = DB::table('assessment_code_questions')
                ->where('code_question_id', (int) ($codeAnswer['question_id'] ?? 0))
                ->where('category', $session->category)
                ->first();
        }
        abort_unless($codeQuestion && isset($codeQuestion->validator_json), 422, 'Практическая задача не найдена.');

        $code = trim((string) ($codeAnswer['code'] ?? ''));
        $codeScore = $this->codeScore((string) $codeQuestion->validator_json, $code);
        $similarity = (($session->_fallback ?? false) === false && Schema::hasTable('assessment_code_answers'))
            ? $this->maxSimilarity((int) $codeQuestion->code_question_id, $code)
            : 0.0;
        if ($similarity >= 88) {
            $this->recordEvent($user, $sessionId, 'code_similarity', 10, ['similarity' => $similarity]);
        }

        $finalScore = round(($choiceScore * 0.78) + ($codeScore * 0.22), 1);
        $risk = min(100, $this->riskFor($sessionId));
        $integrityScore = max(50, 100 - min(50, $risk));
        $reviewRequired = $risk > 20 || $similarity >= 88;
        $threshold = $this->thresholdFor($session->desired_grade);
        $passed = $finalScore >= $threshold;

        $latest = DB::table('assessment_runs')->where('candidate_id', $user->getKey())->orderByDesc('completed_at')->first();
        $cooldownUntil = $latest?->completed_at ? Carbon::parse($latest->completed_at)->addDays(30) : null;
        $locked = $cooldownUntil?->isFuture() ?? false;
        $fallback = ($session->_fallback ?? false) === true;

        $assessmentId = DB::transaction(function () use ($user, $session, $sessionId, $storedChoiceAnswers, $codeQuestion, $code, $codeScore, $similarity, $codeAnswer, $finalScore, $integrityScore, $latest, $passed, $reviewRequired, $locked, $skills, $fallback) {
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

            if ($storedChoiceAnswers && Schema::hasTable('assessment_answers')) {
                DB::table('assessment_answers')->insert(array_map(fn ($row) => [
                    'assessment_id' => $assessmentId,
                    'question_id' => $row['question_id'],
                    'selected_index' => $row['selected_index'],
                    'is_correct' => $row['is_correct'],
                    'response_ms' => $row['response_ms'],
                    'created_at' => now(),
                ], $storedChoiceAnswers));
            }

            if (! $fallback && Schema::hasTable('assessment_code_answers')) {
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
            }

            if (Schema::hasTable('assessment_integrity_events')) {
                $events = $this->eventsFor($sessionId);
                if ($events) {
                    DB::table('assessment_integrity_events')->insert(array_map(fn ($event) => [
                        'candidate_id' => $user->getKey(),
                        'assessment_id' => $assessmentId,
                        'event_type' => $event['event_type'],
                        'weight' => $event['weight'],
                        'metadata' => $event['metadata'],
                        'created_at' => $event['created_at'],
                    ], $events));
                }
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

            if (! $fallback && Schema::hasTable('assessment_live_sessions')) {
                DB::table('assessment_live_sessions')->where('live_session_id', $session->live_session_id)->update([
                    'status' => 'completed',
                    'risk_score' => min(100, $this->riskFor($sessionId)),
                    'completed_at' => now(),
                ]);
            }

            return $assessmentId;
        });

        if ($fallback) {
            session()->forget($this->fallbackSessionKey($sessionId));
        }

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
        if (Schema::hasTable('assessment_live_sessions')) {
            $session = DB::table('assessment_live_sessions')->where('live_session_id', $sessionId)->first();
            if ($session) {
                abort_unless((int) $session->candidate_id === (int) $user->getKey(), 403);
                return $session;
            }
        }

        $fallback = session()->get($this->fallbackSessionKey($sessionId));
        abort_unless(is_array($fallback), 404, 'Live assessment session не найдена.');
        abort_unless((int) ($fallback['candidate_id'] ?? 0) === (int) $user->getKey(), 403);
        return (object) $fallback;
    }

    private function liveSchemaReady(): bool
    {
        return Schema::hasTable('assessment_live_sessions')
            && Schema::hasTable('assessment_live_events')
            && Schema::hasTable('assessment_code_questions')
            && Schema::hasTable('assessment_code_answers');
    }

    private function fallbackSessionKey(int $sessionId): string
    {
        return 'fsp.live_assessment.'.$sessionId;
    }

    private function riskFor(int $sessionId): int
    {
        if (Schema::hasTable('assessment_live_events')) {
            $exists = Schema::hasTable('assessment_live_sessions')
                && DB::table('assessment_live_sessions')->where('live_session_id', $sessionId)->exists();
            if ($exists) {
                return (int) DB::table('assessment_live_events')->where('live_session_id', $sessionId)->sum('weight');
            }
        }

        $stored = session()->get($this->fallbackSessionKey($sessionId), []);
        return (int) collect($stored['events'] ?? [])->sum('weight');
    }

    /** @return array<int,array{event_type:string,weight:int,metadata:?string,created_at:mixed}> */
    private function eventsFor(int $sessionId): array
    {
        if (Schema::hasTable('assessment_live_events') && Schema::hasTable('assessment_live_sessions')
            && DB::table('assessment_live_sessions')->where('live_session_id', $sessionId)->exists()) {
            return DB::table('assessment_live_events')->where('live_session_id', $sessionId)->get()->map(fn ($event) => [
                'event_type' => $event->event_type,
                'weight' => (int) $event->weight,
                'metadata' => $event->metadata,
                'created_at' => $event->created_at,
            ])->all();
        }

        $stored = session()->get($this->fallbackSessionKey($sessionId), []);
        return array_values($stored['events'] ?? []);
    }

    private function fallbackCodeQuestion(string $category): object
    {
        $rows = [
            'Backend' => [900001, 'Idempotency', 'Практика: dedupeLatest', 'Напишите PHP-функцию dedupeLatest(items), которая за O(n) оставляет для каждого id объект с самым свежим updated_at.', 'php', "function dedupeLatest(array \$items): array {\n    // your code\n}", ['min_chars' => 80, 'required_groups' => [['foreach'], ['updated_at'], ['id'], ['return']]], 4],
            'Frontend' => [900002, 'TypeScript', 'Практика: groupBy', 'Реализуйте TypeScript-функцию groupBy<T>(items, key), возвращающую Record<string, T[]> без мутации входного массива.', 'typescript', "function groupBy<T>(items: T[], key: (item: T) => string): Record<string, T[]> {\n  // your code\n}", ['min_chars' => 90, 'required_groups' => [['Record'], ['forEach', 'reduce', 'for ('], ['key('], ['return']]], 4],
            'Data' => [900003, 'SQL', 'Практика: latest row', 'Напишите SQL-запрос, который оставляет только последнюю запись по customer_id, используя ROW_NUMBER() и created_at.', 'sql', "WITH ranked AS (\n  SELECT\n)", ['min_chars' => 70, 'required_groups' => [['row_number'], ['partition by'], ['customer_id'], ['order by'], ['created_at']]], 4],
            'ML/AI' => [900004, 'Validation', 'Практика: temporal split', 'Опишите псевдокод временного train/validation/test split так, чтобы будущее не попадало в train.', 'python', '# timestamps are already parsed', ['min_chars' => 100, 'required_groups' => [['train'], ['validation', 'val'], ['test'], ['time', 'date', 'timestamp']]], 4],
            'DevOps' => [900005, 'CI/CD', 'Практика: safe deploy', 'Опишите YAML/псевдокод безопасного pipeline: build → tests → publish → deploy → health-check → rollback при ошибке.', 'yaml', "steps:\n", ['min_chars' => 100, 'required_groups' => [['build'], ['test'], ['deploy'], ['health', 'check'], ['rollback']]], 4],
            'Full-stack' => [900006, 'Architecture', 'Практика: idempotent API', 'Напишите псевдокод обработчика POST /payments с idempotency key и защитой от двойного списания.', 'typescript', "async function createPayment(req) {\n", ['min_chars' => 110, 'required_groups' => [['idempot'], ['key'], ['payment'], ['return'], ['transaction', 'lock', 'unique']]], 4],
        ];
        $row = $rows[$category] ?? $rows['Backend'];

        return (object) [
            'code_question_id' => $row[0],
            'category' => $category,
            'skill_code' => $row[1],
            'title' => $row[2],
            'prompt' => $row[3],
            'language' => $row[4],
            'starter_code' => $row[5],
            'validator_json' => json_encode($row[6], JSON_UNESCAPED_UNICODE),
            'difficulty' => $row[7],
        ];
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
                if (str_contains($lower, mb_strtolower((string) $needle))) {
                    $hit++;
                    break;
                }
            }
        }
        $groupScore = count($groups) ? ($hit / count($groups)) * 65 : 65;
        return min(100, round($lengthScore + $groupScore, 1));
    }

    private function maxSimilarity(int $questionId, string $code): float
    {
        $normalized = $this->normalizeCode($code);
        if (mb_strlen($normalized) < 30 || ! Schema::hasTable('assessment_code_answers')) {
            return 0;
        }
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
        if (! $node) {
            return;
        }
        $existing = DB::table('candidate_skill_evidence')->where('candidate_id', $user->getKey())->where('skill_node_id', $node->skill_node_id)->where('source_type', 'assessment')->first();
        $data = ['state' => $state, 'source_label' => $source, 'score' => $score, 'verified_at' => now()];
        if ($existing) {
            DB::table('candidate_skill_evidence')->where('evidence_id', $existing->evidence_id)->update($data);
        } else {
            DB::table('candidate_skill_evidence')->insert(['candidate_id' => $user->getKey(), 'skill_node_id' => $node->skill_node_id, 'source_type' => 'assessment', 'created_at' => now(), ...$data]);
        }
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

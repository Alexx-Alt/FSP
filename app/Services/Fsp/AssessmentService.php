<?php

namespace App\Services\Fsp;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AssessmentService
{
    private const GRADES = ['intern', 'junior', 'middle', 'senior', 'lead'];

    private const CATEGORIES = ['Backend', 'Frontend', 'Data', 'ML/AI', 'DevOps', 'Full-stack'];

    /** @return array<string, mixed> */
    public function payload(User $user): array
    {
        $latest = DB::table('assessment_runs')
            ->where('candidate_id', $user->getKey())
            ->orderByDesc('completed_at')
            ->first();

        $history = DB::table('assessment_runs')
            ->where('candidate_id', $user->getKey())
            ->orderByDesc('completed_at')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->assessment_id,
                'category' => $row->category,
                'desiredGrade' => $row->desired_grade,
                'resultGrade' => $row->result_grade,
                'score' => (float) $row->score,
                'integrityScore' => (float) $row->integrity_score,
                'variant' => $row->variant_key,
                'completedAt' => $row->completed_at,
            ])->all();

        $cooldownUntil = $latest?->completed_at
            ? Carbon::parse($latest->completed_at)->addDays(30)
            : null;
        $gradeChangeLocked = $cooldownUntil?->isFuture() ?? false;

        $category = in_array($user->primary_category, self::CATEGORIES, true)
            ? $user->primary_category
            : 'Backend';
        $grade = in_array($user->desired_grade, self::GRADES, true)
            ? $user->desired_grade
            : 'middle';

        $questionRows = $this->questionSet($user, $category, $grade);

        return [
            'categories' => self::CATEGORIES,
            'grades' => self::GRADES,
            'category' => $category,
            'desiredGrade' => $grade,
            'questionSet' => $questionRows->map(fn (array $q) => [
                'id' => $q['id'],
                'skill' => $q['skill'],
                'prompt' => $q['prompt'],
                'options' => $q['options'],
                'difficulty' => $q['difficulty'],
            ])->values()->all(),
            'history' => $history,
            'cooldownUntil' => $cooldownUntil?->toIso8601String(),
            'gradeChangeLocked' => $gradeChangeLocked,
            'demoRetestAllowed' => true,
            'bankSource' => Schema::hasTable('assessment_questions') ? 'database' : 'fallback',
        ];
    }

    /**
     * @param array<int, array{question_id:int|string, selected_index:int, response_ms?:int}> $answers
     * @param array<int, array<string, mixed>> $integrityEvents
     * @return array<string, mixed>
     */
    public function submit(User $user, string $category, string $desiredGrade, array $answers, array $integrityEvents = []): array
    {
        abort_unless(in_array($category, self::CATEGORIES, true), 422, 'Неизвестная категория.');
        abort_unless(in_array($desiredGrade, self::GRADES, true), 422, 'Неизвестный грейд.');

        $bank = $this->bank($category)->keyBy(fn (array $q) => (string) $q['id']);
        $normalized = collect($answers)
            ->filter(fn ($answer) => isset($answer['question_id'], $answer['selected_index']))
            ->unique(fn ($answer) => (string) $answer['question_id'])
            ->values();

        abort_if($normalized->count() < 5, 422, 'Ответьте минимум на пять вопросов.');

        $correct = 0;
        $weightedCorrect = 0.0;
        $weightedTotal = 0.0;
        $answerRows = [];

        foreach ($normalized as $answer) {
            $question = $bank->get((string) $answer['question_id']);
            if (! $question) {
                continue;
            }

            $selected = (int) $answer['selected_index'];
            $isCorrect = $selected === (int) $question['correct'];
            $weight = 1 + ((int) $question['difficulty'] - 1) * 0.18;
            $weightedTotal += $weight;
            $weightedCorrect += $isCorrect ? $weight : 0;
            $correct += $isCorrect ? 1 : 0;
            $answerRows[] = [
                'question_id' => (int) $question['id'],
                'selected_index' => $selected,
                'is_correct' => $isCorrect,
                'response_ms' => min(600000, max(0, (int) ($answer['response_ms'] ?? 0))),
                'skill' => $question['skill'],
                'difficulty' => (int) $question['difficulty'],
            ];
        }

        abort_if(count($answerRows) < 5, 422, 'Набор вопросов устарел. Обновите страницу и повторите.');

        $score = $weightedTotal > 0 ? round($weightedCorrect / $weightedTotal * 100, 1) : 0.0;
        $integrityPenalty = collect($integrityEvents)->sum(function ($event) {
            return match ($event['type'] ?? '') {
                'visibility', 'blur' => 2,
                'copy', 'paste', 'contextmenu' => 4,
                'fullscreen_exit', 'shortcut' => 5,
                default => 1,
            };
        });
        $integrityScore = max(55, 100 - min(45, $integrityPenalty));

        $latest = DB::table('assessment_runs')
            ->where('candidate_id', $user->getKey())
            ->orderByDesc('completed_at')
            ->first();
        $cooldownUntil = $latest?->completed_at ? Carbon::parse($latest->completed_at)->addDays(30) : null;
        $locked = $cooldownUntil?->isFuture() ?? false;
        $passed = $score >= $this->thresholdFor($desiredGrade);
        $variant = 'db-'.Str::lower(Str::random(8)).'-'.now()->format('YmdHis');
        $startedAt = now()->subSeconds(max(90, (int) round(collect($answerRows)->sum('response_ms') / 1000)));

        $assessmentId = DB::transaction(function () use ($user, $category, $desiredGrade, $score, $integrityScore, $variant, $startedAt, $latest, $answerRows, $integrityEvents, $passed, $locked) {
            $assessmentId = DB::table('assessment_runs')->insertGetId([
                'candidate_id' => $user->getKey(),
                'category' => $category,
                'desired_grade' => $desiredGrade,
                'result_grade' => $desiredGrade,
                'score' => $score,
                'integrity_score' => $integrityScore,
                'variant_key' => $variant,
                'started_at' => $startedAt,
                'completed_at' => now(),
                'retest_of' => $latest?->assessment_id,
            ]);

            if (Schema::hasTable('assessment_answers')) {
                DB::table('assessment_answers')->insert(array_map(fn (array $row) => [
                    'assessment_id' => $assessmentId,
                    'question_id' => $row['question_id'],
                    'selected_index' => $row['selected_index'],
                    'is_correct' => $row['is_correct'],
                    'response_ms' => $row['response_ms'],
                    'created_at' => now(),
                ], $answerRows));
            }

            if (Schema::hasTable('assessment_integrity_events') && $integrityEvents) {
                DB::table('assessment_integrity_events')->insert(collect($integrityEvents)->take(100)->map(fn ($event) => [
                    'candidate_id' => $user->getKey(),
                    'assessment_id' => $assessmentId,
                    'event_type' => mb_substr((string) ($event['type'] ?? 'unknown'), 0, 80),
                    'weight' => min(10, max(1, (int) ($event['weight'] ?? 1))),
                    'metadata' => json_encode($event['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ])->all());
            }

            // Assessment updates concrete skill evidence, but never derives the grade from the tree.
            foreach ($answerRows as $answerRow) {
                $skillNodeId = $this->resolveSkillNodeId($category, (string) $answerRow['skill']);
                if (! $skillNodeId) {
                    continue;
                }
                $state = $answerRow['is_correct']
                    ? ((int) $answerRow['difficulty'] >= 3 ? 'confirmed' : 'partial')
                    : 'partial';
                $scoreValue = $answerRow['is_correct'] ? min(100, 68 + ((int) $answerRow['difficulty'] * 6)) : 42;
                $existingEvidence = DB::table('candidate_skill_evidence')
                    ->where('candidate_id', $user->getKey())
                    ->where('skill_node_id', $skillNodeId)
                    ->where('source_type', 'assessment')
                    ->orderByDesc('evidence_id')
                    ->first();
                $evidenceData = [
                    'state' => $state,
                    'source_label' => 'Последний assessment · '.strtoupper($desiredGrade),
                    'score' => $scoreValue,
                    'verified_at' => now(),
                ];
                if ($existingEvidence) {
                    DB::table('candidate_skill_evidence')->where('evidence_id', $existingEvidence->evidence_id)->update($evidenceData);
                } else {
                    DB::table('candidate_skill_evidence')->insert([
                        'candidate_id' => $user->getKey(),
                        'skill_node_id' => $skillNodeId,
                        'source_type' => 'assessment',
                        'created_at' => now(),
                        ...$evidenceData,
                    ]);
                }
            }

            $profileUpdate = [
                'primary_category' => $category,
                'desired_grade' => $desiredGrade,
            ];

            // Cooldown applies to a grade-changing decision, not to a demo retest.
            // A failed higher-level test never destroys an already confirmed lower level.
            if ($passed && (! $locked || $user->confirmed_grade === $desiredGrade || ! $user->confirmed_grade)) {
                $profileUpdate['confirmed_grade'] = $desiredGrade;
                $profileUpdate['grade_status'] = 'confirmed';
            } elseif (! $passed && ! $user->confirmed_grade) {
                $profileUpdate['grade_status'] = 'not_confirmed';
            }

            DB::table('users')->where('user_id', $user->getKey())->update($profileUpdate);

            return $assessmentId;
        });

        return [
            'assessmentId' => $assessmentId,
            'score' => $score,
            'correct' => $correct,
            'total' => count($answerRows),
            'integrityScore' => $integrityScore,
            'passed' => $passed,
            'threshold' => $this->thresholdFor($desiredGrade),
            'gradeChanged' => $passed && (! $locked || $user->confirmed_grade === $desiredGrade || ! $user->confirmed_grade),
            'cooldownUntil' => $cooldownUntil?->toIso8601String(),
        ];
    }

    /** @return Collection<int, array{id:int,skill:string,prompt:string,options:array<int,string>,correct:int,difficulty:int}> */
    private function questionSet(User $user, string $category, string $grade): Collection
    {
        $bank = $this->bank($category);
        $rank = array_search($grade, self::GRADES, true);
        $rank = $rank === false ? 2 : $rank;
        $preferredDifficulty = $rank + 1;

        return $bank
            ->sortBy(function (array $q) use ($user, $grade, $preferredDifficulty) {
                $distance = abs((int) $q['difficulty'] - $preferredDifficulty);
                return sprintf('%02d-%u', $distance, crc32($user->getKey().'|'.$grade.'|'.$q['id'].'|'.now()->format('Y-m-d')));
            })
            ->take(6)
            ->values();
    }

    /** @return Collection<int, array{id:int,skill:string,prompt:string,options:array<int,string>,correct:int,difficulty:int}> */
    private function bank(string $category): Collection
    {
        if (Schema::hasTable('assessment_questions')) {
            $rows = DB::table('assessment_questions')
                ->where('category', $category)
                ->where('active', true)
                ->get();

            if ($rows->count() >= 6) {
                return $rows->map(fn ($row) => [
                    'id' => (int) $row->question_id,
                    'skill' => $row->skill_code,
                    'prompt' => $row->prompt,
                    'options' => json_decode($row->options_json, true, flags: JSON_THROW_ON_ERROR),
                    'correct' => (int) $row->correct_index,
                    'difficulty' => (int) $row->difficulty,
                ]);
            }
        }

        return collect($this->fallbackBank())
            ->where('category', $category)
            ->values()
            ->map(fn (array $q) => [
                'id' => $q['id'], 'skill' => $q['skill'], 'prompt' => $q['prompt'],
                'options' => $q['options'], 'correct' => $q['correct'], 'difficulty' => $q['difficulty'],
            ]);
    }

    private function resolveSkillNodeId(string $category, string $skill): ?int
    {
        $aliases = [
            'Transactions' => ['Транзакции', 'Transactions'],
            'Caching' => ['Redis', 'Caching'],
            'Queues' => ['Очереди', 'Queues'],
            'Architecture' => ['Архитектура', 'Architecture'],
            'Performance' => ['Производительность', 'Performance'],
            'Accessibility' => ['Доступность', 'Accessibility'],
            'Database' => ['Данные', 'Database'],
            'Containers' => ['Контейнеры', 'Docker', 'Containers'],
            'Reliability' => ['Надёжность', 'Reliability'],
            'Security' => ['Безопасность', 'Security'],
            'Validation' => ['Валидация', 'Validation'],
            'Serving' => ['Serving', 'Инференс'],
            'Quality' => ['Качество', 'Quality'],
            'Modeling' => ['Моделирование', 'Modeling'],
            'Warehousing' => ['Хранилища', 'Warehousing'],
            'Streaming' => ['Потоки', 'Streaming'],
            'Experiment' => ['Эксперименты', 'Experiment'],
            'Production' => ['Production'],
            'Incident' => ['Инциденты', 'Incident'],
            'State' => ['State', 'Состояние'],
            'Web' => ['Web'],
        ];
        $names = $aliases[$skill] ?? [$skill];

        $row = DB::table('skill_nodes')
            ->where('category', $category)
            ->where(function ($query) use ($names, $skill) {
                $query->whereIn('name', $names)
                    ->orWhere('code', 'like', '%.'.$skill);
            })
            ->orderByDesc('parent_id')
            ->first();

        return $row ? (int) $row->skill_node_id : null;
    }

    private function thresholdFor(string $grade): int
    {
        return match ($grade) {
            'intern' => 55,
            'junior' => 60,
            'middle' => 70,
            'senior' => 76,
            'lead' => 82,
            default => 70,
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function fallbackBank(): array
    {
        return [
            ['id'=>9001,'category'=>'Backend','skill'=>'API','difficulty'=>1,'prompt'=>'Какой HTTP-метод обычно используют для полного обновления ресурса?','options'=>['GET','PUT','HEAD','TRACE'],'correct'=>1],
            ['id'=>9002,'category'=>'Backend','skill'=>'SQL','difficulty'=>2,'prompt'=>'Что чаще всего ускоряет фильтрацию по селективному столбцу в PostgreSQL?','options'=>['Индекс','VACUUM перед каждым SELECT','Увеличение VARCHAR','Триггер'],'correct'=>0],
            ['id'=>9003,'category'=>'Backend','skill'=>'Transactions','difficulty'=>3,'prompt'=>'Какое свойство ACID гарантирует, что транзакция выполняется целиком или не выполняется вовсе?','options'=>['Consistency','Isolation','Atomicity','Durability'],'correct'=>2],
            ['id'=>9004,'category'=>'Backend','skill'=>'Queues','difficulty'=>3,'prompt'=>'Какой приём помогает безопасно повторять обработку сообщения очереди?','options'=>['Idempotency key','Случайный sleep','Отключение retry','Глобальный mutex навсегда'],'correct'=>0],
            ['id'=>9005,'category'=>'Backend','skill'=>'Caching','difficulty'=>4,'prompt'=>'Что важно предусмотреть при cache-aside после изменения данных?','options'=>['Инвалидацию/обновление кеша','Только увеличение TTL','Отключение БД','Хранение пароля в ключе'],'correct'=>0],
            ['id'=>9006,'category'=>'Backend','skill'=>'Architecture','difficulty'=>5,'prompt'=>'Какой сигнал сильнее всего указывает на необходимость разделить синхронную операцию на фоновые задачи?','options'=>['Операция долгая и допускает eventual consistency','Ответ всегда <10 мс','Нет внешних зависимостей','Одна строка в таблице'],'correct'=>0],

            ['id'=>9101,'category'=>'Frontend','skill'=>'HTML','difficulty'=>1,'prompt'=>'Какой элемент семантически предназначен для основной навигации?','options'=>['nav','span','b','canvas'],'correct'=>0],
            ['id'=>9102,'category'=>'Frontend','skill'=>'TypeScript','difficulty'=>2,'prompt'=>'Что даёт union type `string | null`?','options'=>['Разрешает строку или null','Всегда превращает null в строку','Запрещает строки','Создаёт массив'],'correct'=>0],
            ['id'=>9103,'category'=>'Frontend','skill'=>'Vue','difficulty'=>3,'prompt'=>'Для чего в Vue чаще используют computed?','options'=>['Для производного реактивного значения','Для прямого доступа к DOM вместо ref','Для HTTP-сервера','Для CSS reset'],'correct'=>0],
            ['id'=>9104,'category'=>'Frontend','skill'=>'Performance','difficulty'=>3,'prompt'=>'Что обычно уменьшает первоначальный JS bundle?','options'=>['Code splitting','Дублирование зависимостей','Inline всех изображений base64','Отключение кеша'],'correct'=>0],
            ['id'=>9105,'category'=>'Frontend','skill'=>'Accessibility','difficulty'=>4,'prompt'=>'Что обязательно для интерактивного элемента, управляемого только иконкой?','options'=>['Доступное имя/aria-label','Отрицательный tabindex всегда','display:none','Только title'],'correct'=>0],
            ['id'=>9106,'category'=>'Frontend','skill'=>'Architecture','difficulty'=>5,'prompt'=>'Когда выгодно вводить дизайн-систему компонентов?','options'=>['Когда интерфейсы повторяются между продуктами и нужна согласованность','Когда страница одна и одноразовая','Только ради увеличения bundle','Чтобы убрать типизацию'],'correct'=>0],

            ['id'=>9201,'category'=>'Data','skill'=>'SQL','difficulty'=>1,'prompt'=>'Какая операция объединяет строки двух таблиц по условию?','options'=>['JOIN','VACUUM','GRANT','COMMIT'],'correct'=>0],
            ['id'=>9202,'category'=>'Data','skill'=>'ETL','difficulty'=>2,'prompt'=>'Что означает idempotent pipeline?','options'=>['Повторный запуск не портит результат','Запускается только один раз','Не имеет логов','Работает без данных'],'correct'=>0],
            ['id'=>9203,'category'=>'Data','skill'=>'Warehousing','difficulty'=>3,'prompt'=>'Что обычно хранится в fact table?','options'=>['Измеряемые события/метрики','Только справочник стран','CSS-токены','Пароли пользователей'],'correct'=>0],
            ['id'=>9204,'category'=>'Data','skill'=>'Streaming','difficulty'=>3,'prompt'=>'Что помогает избежать повторной обработки события в consumer?','options'=>['Хранение offset/idempotency','Удаление ключа события','Случайный порядок','Отключение checkpoint'],'correct'=>0],
            ['id'=>9205,'category'=>'Data','skill'=>'Quality','difficulty'=>4,'prompt'=>'Какой контроль полезен для проверки полноты дневной загрузки?','options'=>['Сравнение ожидаемого и фактического числа записей','Проверка цвета графика','Смена кодировки каждый день','Удаление null без анализа'],'correct'=>0],
            ['id'=>9206,'category'=>'Data','skill'=>'Modeling','difficulty'=>5,'prompt'=>'Когда денормализация аналитической модели оправдана?','options'=>['Когда чтение доминирует и нужны простые быстрые запросы','Всегда','Только для OLTP-транзакций','Когда запрещены индексы'],'correct'=>0],

            ['id'=>9301,'category'=>'ML/AI','skill'=>'Metrics','difficulty'=>1,'prompt'=>'Какая метрика особенно важна при сильном дисбалансе классов?','options'=>['F1/PR-AUC','Только accuracy','FPS интерфейса','Размер шрифта'],'correct'=>0],
            ['id'=>9302,'category'=>'ML/AI','skill'=>'Validation','difficulty'=>2,'prompt'=>'Зачем нужен отдельный validation set?','options'=>['Для выбора модели без подглядывания в test','Для хранения логов','Для увеличения batch size','Для деплоя CSS'],'correct'=>0],
            ['id'=>9303,'category'=>'ML/AI','skill'=>'Leakage','difficulty'=>3,'prompt'=>'Что такое data leakage?','options'=>['Использование информации, недоступной в момент реального предсказания','Удаление дубликатов','Сжатие модели','Квантизация'],'correct'=>0],
            ['id'=>9304,'category'=>'ML/AI','skill'=>'Serving','difficulty'=>3,'prompt'=>'Что важно мониторить после выката модели?','options'=>['Drift, latency и качество','Только размер README','Количество git-веток','Цвет кнопок'],'correct'=>0],
            ['id'=>9305,'category'=>'ML/AI','skill'=>'Experiment','difficulty'=>4,'prompt'=>'Что делает эксперимент воспроизводимым?','options'=>['Фиксация данных, seed, кода и параметров','Случайные параметры без логов','Только название модели','Удаление окружения'],'correct'=>0],
            ['id'=>9306,'category'=>'ML/AI','skill'=>'Production','difficulty'=>5,'prompt'=>'Когда batch inference предпочтительнее online inference?','options'=>['Когда ответы не нужны мгновенно и объём большой','Когда нужна реакция за миллисекунды','Всегда','Только для изображений'],'correct'=>0],

            ['id'=>9401,'category'=>'DevOps','skill'=>'Containers','difficulty'=>1,'prompt'=>'Что описывает Dockerfile?','options'=>['Сборку образа','DNS-зону','SQL-транзакцию','UI-маршрут'],'correct'=>0],
            ['id'=>9402,'category'=>'DevOps','skill'=>'CI/CD','difficulty'=>2,'prompt'=>'Зачем разделять build и deploy?','options'=>['Для воспроизводимого артефакта и контролируемой доставки','Чтобы не хранить артефакты','Чтобы отключить тесты','Только ради логов'],'correct'=>0],
            ['id'=>9403,'category'=>'DevOps','skill'=>'Kubernetes','difficulty'=>3,'prompt'=>'Что делает readiness probe?','options'=>['Показывает, готов ли pod принимать трафик','Всегда перезапускает pod','Создаёт namespace','Шифрует secret'],'correct'=>0],
            ['id'=>9404,'category'=>'DevOps','skill'=>'IaC','difficulty'=>3,'prompt'=>'Главное преимущество Infrastructure as Code?','options'=>['Версионируемая и воспроизводимая инфраструктура','Отсутствие ревью','Ручные изменения production','Нет state'],'correct'=>0],
            ['id'=>9405,'category'=>'DevOps','skill'=>'Reliability','difficulty'=>4,'prompt'=>'Что выражает SLO?','options'=>['Целевой уровень надёжности сервиса','Версию ОС','Количество разработчиков','Размер контейнера'],'correct'=>0],
            ['id'=>9406,'category'=>'DevOps','skill'=>'Incident','difficulty'=>5,'prompt'=>'Что важнее всего после серьёзного инцидента?','options'=>['Blameless postmortem и системные действия','Найти виноватого','Удалить логи','Скрыть метрики'],'correct'=>0],

            ['id'=>9501,'category'=>'Full-stack','skill'=>'Web','difficulty'=>1,'prompt'=>'Что связывает frontend с backend в типичном web-приложении?','options'=>['HTTP API','Только CSS','DNS TXT','Git tag'],'correct'=>0],
            ['id'=>9502,'category'=>'Full-stack','skill'=>'Database','difficulty'=>2,'prompt'=>'Где должна проверяться авторизация на изменение ресурса?','options'=>['На сервере','Только в кнопке интерфейса','В CSS','В localStorage клиента'],'correct'=>0],
            ['id'=>9503,'category'=>'Full-stack','skill'=>'State','difficulty'=>3,'prompt'=>'Что лучше хранить как server state?','options'=>['Данные, общие между устройствами и пользователями','Hover кнопки','Открыт ли локальный tooltip','Размер окна'],'correct'=>0],
            ['id'=>9504,'category'=>'Full-stack','skill'=>'Security','difficulty'=>3,'prompt'=>'Как защитить серверный endpoint от недоверенных входных данных?','options'=>['Валидация и авторизация на сервере','Скрыть кнопку','Минифицировать JS','Поменять шрифт'],'correct'=>0],
            ['id'=>9505,'category'=>'Full-stack','skill'=>'Performance','difficulty'=>4,'prompt'=>'Как найти узкое место end-to-end запроса?','options'=>['Трассировка/метрики по слоям','Оптимизировать всё вслепую','Отключить БД','Удалить тесты'],'correct'=>0],
            ['id'=>9506,'category'=>'Full-stack','skill'=>'Architecture','difficulty'=>5,'prompt'=>'Когда BFF (Backend for Frontend) может быть полезен?','options'=>['Когда клиенту нужен специализированный агрегированный API','Всегда','Только без frontend','Когда API запрещён'],'correct'=>0],
        ];
    }
}

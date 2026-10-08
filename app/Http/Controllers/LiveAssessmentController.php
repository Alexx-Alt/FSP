<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Fsp\LiveAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LiveAssessmentController extends Controller
{
    public function show(Request $request, LiveAssessmentService $service): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);

        $this->enableSignedUnsignedSubtraction();

        return Inertia::render('AssessmentSession', [
            'session' => $service->start($user),
        ]);
    }

    public function event(Request $request, int $session, LiveAssessmentService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'type' => ['required', 'string', 'max:80'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:10'],
            'metadata' => ['nullable'],
        ]);
        $metadata = $data['metadata'] ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?: [];
        }
        if (! is_array($metadata)) {
            $metadata = [];
        }

        $service->recordEvent(
            $user,
            $session,
            $data['type'],
            (int) ($data['weight'] ?? 1),
            $metadata,
        );

        return response()->json(['ok' => true]);
    }

    public function submit(Request $request, int $session, LiveAssessmentService $service): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'answers' => ['required', 'array', 'min:5', 'max:10'],
            'answers.*.kind' => ['required', 'in:choice,code'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.selected_index' => ['nullable', 'integer', 'min:0', 'max:12'],
            'answers.*.code' => ['nullable', 'string', 'max:20000'],
            'answers.*.response_ms' => ['nullable', 'integer', 'min:0', 'max:900000'],
        ]);

        $result = $service->finish($user, $session, $data['answers']);
        $message = $result['reviewRequired']
            ? 'Тест завершён. Integrity-сигналы требуют ручной проверки; грейд пока не изменён.'
            : 'Тест завершён: '.$result['score'].'/100, практическая часть '.$result['codeScore'].'/100.';

        return to_route('candidate.center')->with('success', $message);
    }

    /**
     * assessment_questions.difficulty хранится как UNSIGNED.
     * В MySQL выражение `difficulty - 3` для значений 1/2 может выбрасывать
     * BIGINT UNSIGNED out of range. Этот режим заставляет результат вычитания
     * быть знаковым и сохраняет текущую схему БД без обязательного ALTER TABLE.
     */
    private function enableSignedUnsignedSubtraction(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $row = DB::selectOne('SELECT @@SESSION.sql_mode AS sql_mode');
        $modes = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($row->sql_mode ?? ''))
        )));

        if (in_array('NO_UNSIGNED_SUBTRACTION', $modes, true)) {
            return;
        }

        $modes[] = 'NO_UNSIGNED_SUBTRACTION';
        DB::statement('SET SESSION sql_mode = ?', [implode(',', array_unique($modes))]);
    }
}

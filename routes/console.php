<?php

use App\Services\Fsp\MatchEngine;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fsp:validate {--json : Print machine-readable JSON}', function (MatchEngine $engine) {
    $file = base_path('database/fsp_match_v6/validation_pairs.json');
    if (! is_file($file)) {
        $this->error('Validation dataset not found: '.$file);
        return 2;
    }

    $pairs = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    $rows = [];
    $tp = $fp = $tn = $fn = 0;
    $relevantScores = [];
    $irrelevantScores = [];

    foreach ($pairs as $pair) {
        $candidateId = DB::table('users')->where('email', $pair['candidate_email'])->value('user_id');
        if (! $candidateId) {
            $this->warn('Skip missing candidate: '.$pair['candidate_email']);
            continue;
        }

        $result = $engine->compare((int) $candidateId, (int) $pair['job_id']);
        $expected = (bool) $pair['relevant'];
        $predicted = in_array($result['route'], ['invite', 'clarify'], true);
        $score = (int) $result['score'];

        if ($expected) {
            $relevantScores[] = $score;
        } else {
            $irrelevantScores[] = $score;
        }

        if ($expected && $predicted) {
            $tp++;
        } elseif (! $expected && $predicted) {
            $fp++;
        } elseif (! $expected && ! $predicted) {
            $tn++;
        } else {
            $fn++;
        }

        $rows[] = [
            'candidate' => $pair['candidate_email'],
            'job' => (int) $pair['job_id'],
            'expected' => $expected ? 'relevant' : 'not relevant',
            'route' => $result['route'],
            'score' => $score,
            'ok' => $expected === $predicted ? '✓' : '×',
        ];
    }

    $precision = ($tp + $fp) ? $tp / ($tp + $fp) : 0;
    $recall = ($tp + $fn) ? $tp / ($tp + $fn) : 0;
    $accuracy = ($tp + $tn + $fp + $fn) ? ($tp + $tn) / ($tp + $tn + $fp + $fn) : 0;
    $f1 = ($precision + $recall) ? 2 * $precision * $recall / ($precision + $recall) : 0;
    $avgRelevant = $relevantScores ? array_sum($relevantScores) / count($relevantScores) : 0;
    $avgIrrelevant = $irrelevantScores ? array_sum($irrelevantScores) / count($irrelevantScores) : 0;

    $metrics = [
        'pairs' => count($rows),
        'precision' => round($precision * 100, 1),
        'recall' => round($recall * 100, 1),
        'f1' => round($f1 * 100, 1),
        'accuracy' => round($accuracy * 100, 1),
        'avg_relevant_score' => round($avgRelevant, 1),
        'avg_irrelevant_score' => round($avgIrrelevant, 1),
        'score_separation' => round($avgRelevant - $avgIrrelevant, 1),
        'confusion' => compact('tp', 'fp', 'tn', 'fn'),
    ];

    if ($this->option('json')) {
        $this->line(json_encode(['metrics' => $metrics, 'pairs' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return 0;
    }

    $this->info('FSP Match validation');
    $this->table(['Candidate', 'Job', 'Expected', 'Route', 'Score', 'OK'], $rows);
    $this->newLine();
    $this->table(['Metric', 'Value'], [
        ['Precision', $metrics['precision'].'%'],
        ['Recall', $metrics['recall'].'%'],
        ['F1', $metrics['f1'].'%'],
        ['Accuracy', $metrics['accuracy'].'%'],
        ['Avg relevant score', $metrics['avg_relevant_score']],
        ['Avg irrelevant score', $metrics['avg_irrelevant_score']],
        ['Score separation', $metrics['score_separation']],
    ]);
    $this->comment('Dataset: database/fsp_match_v6/validation_pairs.json');
    $this->comment('Decision boundary for this reproducible check: invite/clarify = relevant; perspective/observe = not relevant.');
    return 0;
})->purpose('Validate explainable Match Engine on the synthetic vacancy-candidate dataset');

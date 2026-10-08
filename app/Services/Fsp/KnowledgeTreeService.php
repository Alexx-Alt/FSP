<?php

namespace App\Services\Fsp;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KnowledgeTreeService
{
    /**
     * Возвращает дерево компетенций вместе с доказательствами конкретного кандидата.
     * Грейд здесь намеренно не вычисляется: он подтверждается только assessment.
     *
     * Важно: у одного skill_node может быть несколько evidence-записей
     * (assessment, FSP, ручное подтверждение и т.д.). В дерево должен попадать
     * ровно один узел навыка, поэтому evidence выбирается отдельно, а не через
     * обычный LEFT JOIN, который размножал узлы в графе.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forCandidate(int $candidateId): array
    {
        $category = DB::table('users')->where('user_id', $candidateId)->value('primary_category');

        $nodes = DB::table('skill_nodes')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->select(
                'skill_node_id',
                'parent_id',
                'code',
                'name',
                'category',
                'sort_order'
            )
            ->orderBy('sort_order')
            ->orderBy('skill_node_id')
            ->get();

        if ($nodes->isEmpty()) {
            return [];
        }

        $stateWeight = [
            'confirmed' => 4,
            'partial' => 3,
            'claimed' => 2,
            'unknown' => 1,
        ];

        $evidenceBySkill = DB::table('candidate_skill_evidence')
            ->where('candidate_id', $candidateId)
            ->whereIn('skill_node_id', $nodes->pluck('skill_node_id'))
            ->get()
            ->sortByDesc(function ($row) use ($stateWeight) {
                $weight = $stateWeight[$row->state ?? 'unknown'] ?? 0;
                $score = (int) ($row->score ?? 0);
                $verified = (string) ($row->verified_at ?? '');
                $id = (int) ($row->evidence_id ?? 0);

                return sprintf('%02d-%03d-%s-%020d', $weight, $score, $verified, $id);
            })
            ->groupBy('skill_node_id')
            ->map(fn (Collection $rows) => $rows->first());

        $nodes = $nodes->map(function ($node) use ($evidenceBySkill) {
            $evidence = $evidenceBySkill->get($node->skill_node_id);

            $node->state = $evidence?->state ?? 'unknown';
            $node->source_type = $evidence?->source_type;
            $node->source_label = $evidence?->source_label;
            $node->score = $evidence?->score;
            $node->verified_at = $evidence?->verified_at;

            return $node;
        });

        return $this->nest($nodes);
    }

    /** @return array<int, array<string, mixed>> */
    private function nest(Collection $nodes, ?int $parentId = null): array
    {
        return $nodes
            ->filter(fn ($node) => ($node->parent_id === null ? null : (int) $node->parent_id) === $parentId)
            ->map(function ($node) use ($nodes) {
                return [
                    'id' => (int) $node->skill_node_id,
                    'code' => $node->code,
                    'name' => $node->name,
                    'category' => $node->category,
                    'state' => $node->state ?? 'unknown',
                    'sourceType' => $node->source_type,
                    'sourceLabel' => $node->source_label,
                    'score' => $node->score === null ? null : (float) $node->score,
                    'verifiedAt' => $node->verified_at,
                    'children' => $this->nest($nodes, (int) $node->skill_node_id),
                ];
            })
            ->values()
            ->all();
    }
}

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
     * @return array<int, array<string, mixed>>
     */
    public function forCandidate(int $candidateId): array
    {
        $category = DB::table('users')->where('user_id', $candidateId)->value('primary_category');

        $nodes = DB::table('skill_nodes')
            ->leftJoin('candidate_skill_evidence as evidence', function ($join) use ($candidateId) {
                $join->on('evidence.skill_node_id', '=', 'skill_nodes.skill_node_id')
                    ->where('evidence.candidate_id', '=', $candidateId);
            })
            ->when($category, fn ($query) => $query->where('skill_nodes.category', $category))
            ->select(
                'skill_nodes.skill_node_id',
                'skill_nodes.parent_id',
                'skill_nodes.code',
                'skill_nodes.name',
                'skill_nodes.category',
                'skill_nodes.sort_order',
                'evidence.state',
                'evidence.source_type',
                'evidence.source_label',
                'evidence.score',
                'evidence.verified_at'
            )
            ->orderBy('skill_nodes.sort_order')
            ->get();

        return $this->nest($nodes);
    }

    /** @return array<int, array<string, mixed>> */
    private function nest(Collection $nodes, ?int $parentId = null): array
    {
        return $nodes
            ->filter(fn ($node) => ($node->parent_id === null ? null : (int) $node->parent_id) === $parentId)
            ->map(function ($node) use ($nodes) {
                return [
                    'id' => $node->skill_node_id,
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

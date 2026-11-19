<?php

namespace Lab;

/**
 * Brute-force nearest-neighbour search over normalised vectors, plus the small "same app area" nudge.
 * This is the piece that would be swapped for pgvector / Qdrant at scale.
 */
class DuplicateFinder
{
    public float $searchMilliseconds = 0.0;

    public int $comparedCount = 0;

    /**
     * +$strength when both posts name the same area, -$strength when they name different ones.
     * "other" (or no area) is neutral: the member didn't commit to an area.
     */
    public static function areaAdjustment(?string $areaA, ?string $areaB, float $strength): float
    {
        $known = fn (?string $area): bool => $area !== null && $area !== '' && $area !== 'other';

        if ($strength <= 0 || ! $known($areaA) || ! $known($areaB)) {
            return 0.0;
        }

        return $areaA === $areaB ? $strength : -$strength;
    }

    /**
     * @param  list<float>  $queryVector
     * @param  list<array{id: int, area?: ?string, vector: list<float>}>  $candidates
     * @return list<array<string, mixed>> candidates (minus their vector) with `similarity`, `area_adjustment` and `score`, best first
     */
    public function find(array $queryVector, ?string $queryArea, array $candidates, float $minScore, int $maxResults, float $areaStrength = 0.0): array
    {
        $startedAt = hrtime(true);
        $dimensions = count($queryVector);
        $matches = [];

        foreach ($candidates as $candidate) {
            $vector = $candidate['vector'];
            $similarity = 0.0;

            for ($i = 0; $i < $dimensions; $i++) {
                $similarity += $queryVector[$i] * $vector[$i];
            }

            $adjustment = self::areaAdjustment($queryArea, $candidate['area'] ?? null, $areaStrength);
            $score = $similarity + $adjustment;

            if ($score >= $minScore) {
                unset($candidate['vector']);
                $candidate['similarity'] = round($similarity, 4);
                $candidate['area_adjustment'] = round($adjustment, 4);
                $candidate['score'] = round($score, 4);
                $matches[] = $candidate;
            }
        }

        usort($matches, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $this->comparedCount = count($candidates);
        $this->searchMilliseconds = (hrtime(true) - $startedAt) / 1e6;

        return array_slice($matches, 0, $maxResults);
    }
}

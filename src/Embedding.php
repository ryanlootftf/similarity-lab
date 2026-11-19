<?php

namespace Lab;

/**
 * Pure-PHP helpers shared by the static embedder and everything that consumes it.
 * Extracted so the whole pipeline has zero native/FFI dependencies.
 */
final class Embedding
{
    /**
     * Scales a vector to unit length, so cosine similarity reduces to a dot product.
     *
     * @param  list<float>  $vector
     * @return list<float>
     */
    public static function normalize(array $vector): array
    {
        $length = sqrt(array_sum(array_map(fn (float $value): float => $value * $value, $vector)));

        if ($length == 0.0) {
            return $vector;
        }

        return array_map(fn (float $value): float => $value / $length, $vector);
    }

    /**
     * The text a post is compared on: title carries most of the signal, description adds context.
     */
    public static function textFor(string $title, string $description): string
    {
        return trim($title).'. '.trim($description);
    }
}

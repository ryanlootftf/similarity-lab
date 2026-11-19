<?php

namespace Lab;

use Normalizer;
use RuntimeException;

/**
 * Pure-PHP port of the Hugging Face "BertNormalizer + BertPreTokenizer + WordPiece" pipeline,
 * configured from a model's tokenizer.json. No special tokens are added (static models don't use them).
 */
class WordPieceTokenizer
{
    /** @var array<string, int> */
    private array $vocab;

    private int $unknownId;

    private string $subwordPrefix;

    private int $maxCharsPerWord;

    private bool $cleanText = true;

    private bool $handleChineseChars = true;

    private bool $stripAccents = true;

    private bool $lowercase = true;

    public function __construct(string $tokenizerJsonPath)
    {
        $config = json_decode(file_get_contents($tokenizerJsonPath), true, flags: JSON_THROW_ON_ERROR);
        $model = $config['model'];

        if (($model['type'] ?? null) !== 'WordPiece') {
            throw new RuntimeException('Only WordPiece tokenizers are supported, got '.($model['type'] ?? 'unknown').'.');
        }

        $this->vocab = $model['vocab'];
        $this->unknownId = $this->vocab[$model['unk_token']] ?? throw new RuntimeException('Unknown token missing from vocab.');
        $this->subwordPrefix = $model['continuing_subword_prefix'] ?? '##';
        $this->maxCharsPerWord = $model['max_input_chars_per_word'] ?? 100;

        $normalizer = $config['normalizer'] ?? null;
        if ($normalizer !== null) {
            if ($normalizer['type'] !== 'BertNormalizer') {
                throw new RuntimeException("Unsupported normalizer {$normalizer['type']}.");
            }
            $this->cleanText = $normalizer['clean_text'] ?? true;
            $this->handleChineseChars = $normalizer['handle_chinese_chars'] ?? true;
            $this->lowercase = $normalizer['lowercase'] ?? true;
            // HF semantics: strip_accents null means "follow lowercase".
            $this->stripAccents = $normalizer['strip_accents'] ?? $this->lowercase;
        }
    }

    public function unknownId(): int
    {
        return $this->unknownId;
    }

    /**
     * @return list<int>
     */
    public function encode(string $text): array
    {
        $ids = [];

        foreach ($this->preTokenize($this->normalize($text)) as $word) {
            array_push($ids, ...$this->wordPiece($word));
        }

        return $ids;
    }

    private function normalize(string $text): string
    {
        if ($this->cleanText) {
            $text = preg_replace('/[\x{0000}\x{FFFD}]|[^\P{Cc}\t\n\r]|\p{Cf}/u', '', $text);
            $text = preg_replace('/\s/u', ' ', $text);
        }

        if ($this->handleChineseChars) {
            $text = preg_replace(
                '/([\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{20000}-\x{2A6DF}\x{2A700}-\x{2B73F}\x{2B740}-\x{2B81F}\x{2B820}-\x{2CEAF}\x{F900}-\x{FAFF}\x{2F800}-\x{2FA1F}])/u',
                ' $1 ',
                $text,
            );
        }

        if ($this->stripAccents) {
            $text = preg_replace('/\p{Mn}/u', '', Normalizer::normalize($text, Normalizer::FORM_D));
        }

        return $this->lowercase ? mb_strtolower($text) : $text;
    }

    /**
     * Split on whitespace, then isolate each punctuation character (BERT's definition: ASCII symbols + Unicode P*).
     *
     * @return list<string>
     */
    private function preTokenize(string $text): array
    {
        $words = [];

        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
            foreach (preg_split('/([\p{P}!-\/:-@\[-`{-~])/u', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $word) {
                $words[] = $word;
            }
        }

        return $words;
    }

    /**
     * Greedy longest-match-first subword split; an unsplittable word becomes a single unknown token.
     *
     * @return list<int>
     */
    private function wordPiece(string $word): array
    {
        $chars = mb_str_split($word);
        $length = count($chars);

        if ($length > $this->maxCharsPerWord) {
            return [$this->unknownId];
        }

        $ids = [];
        $start = 0;

        while ($start < $length) {
            $end = $length;
            $found = null;

            while ($start < $end) {
                $piece = implode('', array_slice($chars, $start, $end - $start));
                if ($start > 0) {
                    $piece = $this->subwordPrefix.$piece;
                }
                if (isset($this->vocab[$piece])) {
                    $found = $this->vocab[$piece];
                    break;
                }
                $end--;
            }

            if ($found === null) {
                return [$this->unknownId];
            }

            $ids[] = $found;
            $start = $end;
        }

        return $ids;
    }
}

<?php

/**
 * One-time conversion: keep the first N dimensions of a Matryoshka static model (optionally as float16)
 * and write a smaller .safetensors next to a copy of its tokenizer.
 * Usage: php bin/trim-static.php <source-dir> <target-dir> <dimensions> [--f16]
 */
[$_, $source, $target, $dimensions] = $argv + [null, null, null, null];
$halfPrecision = in_array('--f16', $argv, true);
$dimensions = (int) $dimensions;

if (! $source || ! $target || $dimensions < 1) {
    fwrite(STDERR, "Usage: php bin/trim-static.php <source-dir> <target-dir> <dimensions> [--f16]\n");
    exit(1);
}

$in = fopen("{$source}/model.safetensors", 'rb');
$headerLength = unpack('P', fread($in, 8))[1];
$header = json_decode(fread($in, $headerLength), true, flags: JSON_THROW_ON_ERROR);
$name = isset($header['embeddings']) ? 'embeddings' : 'embedding.weight';
$tensor = $header[$name];
[$rows, $storedDimensions] = $tensor['shape'];

if ($tensor['dtype'] !== 'F32' || $dimensions > $storedDimensions) {
    fwrite(STDERR, "Expected an F32 tensor with at least {$dimensions} dimensions.\n");
    exit(1);
}

/** IEEE 754 float32 -> float16 bits, round-to-nearest. */
$toHalf = function (float $value): int {
    $bits = unpack('V', pack('g', $value))[1];
    $sign = ($bits >> 16) & 0x8000;
    $exponent = (($bits >> 23) & 0xFF) - 127 + 15;
    $mantissa = $bits & 0x7FFFFF;

    if ($exponent >= 31) {
        return $sign | 0x7C00;
    }
    if ($exponent <= 0) {
        if ($exponent < -10) {
            return $sign;
        }
        $mantissa |= 0x800000;
        $shift = 14 - $exponent;

        return $sign | (($mantissa + (1 << ($shift - 1))) >> $shift);
    }

    $half = $sign | ($exponent << 10) | ($mantissa >> 13);

    return ($mantissa & 0x1000) ? $half + 1 : $half;
};

$dtype = $halfPrecision ? 'F16' : 'F32';
$bytesPerValue = $halfPrecision ? 2 : 4;
$newHeader = json_encode([$name => [
    'dtype' => $dtype,
    'shape' => [$rows, $dimensions],
    'data_offsets' => [0, $rows * $dimensions * $bytesPerValue],
]]);
$newHeader .= str_repeat(' ', (8 - strlen($newHeader) % 8) % 8);

if (! is_dir($target)) {
    mkdir($target, 0777, true);
}
$out = fopen("{$target}/model.safetensors", 'wb');
fwrite($out, pack('P', strlen($newHeader)).$newHeader);

$rowsOffset = 8 + $headerLength + $tensor['data_offsets'][0];
for ($row = 0; $row < $rows; $row++) {
    fseek($in, $rowsOffset + $row * $storedDimensions * 4);
    $bytes = fread($in, $dimensions * 4);
    fwrite($out, $halfPrecision ? pack('v*', ...array_map($toHalf, unpack('g*', $bytes))) : $bytes);
}

fclose($out);
copy("{$source}/tokenizer.json", "{$target}/tokenizer.json");

printf("Wrote %s: %d x %d %s, %.1f MB\n", $target, $rows, $dimensions, $dtype, filesize("{$target}/model.safetensors") / 1048576);

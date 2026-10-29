<?php

namespace App\Core\Usage;

use App\Core\Claude\GenerateResult;
use App\Models\AiUsage;

/**
 * Mencatat pemakaian token dan estimasi biaya (USD) setiap panggilan Claude.
 */
class UsageRecorder
{
    public function record(GenerateResult $result, string $model, array $context): AiUsage
    {
        $u = $result->usage;

        return AiUsage::create(array_merge($context, [
            'model' => $model,
            'input_tokens' => (int) ($u['input_tokens'] ?? 0),
            'output_tokens' => (int) ($u['output_tokens'] ?? 0),
            'cache_read_tokens' => (int) ($u['cache_read_input_tokens'] ?? 0),
            'cache_write_tokens' => (int) ($u['cache_creation_input_tokens'] ?? 0),
            'cost_usd' => self::cost($model, $u),
        ]));
    }

    /** Estimasi biaya: cache read 0,1× dan cache write 1,25× harga input. */
    public static function cost(string $model, array $usage): float
    {
        $price = config("claude.models.{$model}") ?? ['input' => 0, 'output' => 0];
        $in = (float) $price['input'] / 1_000_000;
        $out = (float) $price['output'] / 1_000_000;

        return round(
            ($usage['input_tokens'] ?? 0) * $in
            + ($usage['cache_read_input_tokens'] ?? 0) * $in * 0.1
            + ($usage['cache_creation_input_tokens'] ?? 0) * $in * 1.25
            + ($usage['output_tokens'] ?? 0) * $out,
            6
        );
    }
}

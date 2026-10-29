<?php

namespace App\Core\Modules;

use App\Models\GenerationItem;

/**
 * Rencana satu Job: langkah-langkah berurutan dalam satu container.
 */
final class JobPlan
{
    /**
     * @param  JobStep[]  $steps
     * @param  string[]  $skills  skill Anthropic yang dimuat di container
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $steps,
        public readonly ?GenerationItem $item = null,
        public readonly array $skills = ['docx'],
    ) {}
}

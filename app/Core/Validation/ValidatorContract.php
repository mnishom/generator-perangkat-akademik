<?php

namespace App\Core\Validation;

/**
 * Pemeriksa mandiri (lapis 2) yang didaftarkan modul. Tidak bergantung pada laporan Claude.
 */
interface ValidatorContract
{
    /** @return CheckResult[] */
    public function validate(ValidationContext $context): array;
}

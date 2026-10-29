<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Pembatasan login ke domain email kampus.
 */
class EmailDomainPolicy
{
    public function __construct(private readonly string $domain) {}

    public static function fromConfig(): self
    {
        return new self(Str::lower((string) config('platform.allowed_email_domain')));
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function emailAllowed(?string $email): bool
    {
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return Str::endsWith(Str::lower(trim($email)), '@'.$this->domain);
    }

    /**
     * Klaim `hd` (hosted domain) Google Workspace harus sama persis dengan domain kampus.
     */
    public function hostedDomainAllowed(?string $hd): bool
    {
        return is_string($hd) && Str::lower($hd) === $this->domain;
    }
}

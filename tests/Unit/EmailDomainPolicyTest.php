<?php

use App\Support\EmailDomainPolicy;

it('memeriksa akhiran email kampus secara ketat', function (string $email, bool $ok) {
    expect((new EmailDomainPolicy('harkatnegeri.ac.id'))->emailAllowed($email))->toBe($ok);
})->with([
    ['dosen@harkatnegeri.ac.id', true],
    ['DOSEN@HarkatNegeri.ac.id', true],
    ['dosen@gmail.com', false],
    ['dosen@harkatnegeri.ac.id.evil.com', false],
    ['dosen@xharkatnegeri.ac.id', false],
    ['dosen@mhs.harkatnegeri.ac.id', false],
    ['bukan-email', false],
]);

it('memeriksa klaim hd', function () {
    $p = new EmailDomainPolicy('harkatnegeri.ac.id');
    expect($p->hostedDomainAllowed('harkatnegeri.ac.id'))->toBeTrue()
        ->and($p->hostedDomainAllowed(null))->toBeFalse()
        ->and($p->hostedDomainAllowed('gmail.com'))->toBeFalse();
});

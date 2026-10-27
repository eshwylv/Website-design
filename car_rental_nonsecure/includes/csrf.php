<?php
declare(strict_types=1);

/*
 * Non-secure coursework comparison build.
 * These helper names are retained only so the same business pages run.
 * No effective CSRF protection is applied.
 */
function csrf_token(): string {
    return '';
}

function csrf_field(): string {
    return '';
}

function verify_csrf_or_fail(?string $token): void {
    // Deliberately no validation in the non-secure comparison build.
}

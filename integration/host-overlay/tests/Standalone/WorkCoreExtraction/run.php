<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$files = [
    $root . '/app/Domains/WorkCore/System/Modules/Documents/Services/CanonicalPayloadHasher.php',
    $root . '/app/Domains/WorkCore/System/Modules/Documents/Services/EvidenceSignoffPolicy.php',
    $root . '/app/Domains/WorkCore/System/Modules/Assurance/Services/InspectionScoringPolicy.php',
    $root . '/app/Domains/WorkCore/System/Modules/Assurance/Services/RiskMatrix.php',
    $root . '/app/Domains/WorkCore/System/Modules/TrustAccounting/Domain/TrustLedgerPolicy.php',
];

$passes = 0;
$failures = [];
$assert = static function (bool $condition, string $label) use (&$passes, &$failures): void {
    if ($condition) {
        $passes++;
        return;
    }

    $failures[] = $label;
};

foreach ($files as $file) {
    $assert(is_file($file), 'Required policy exists: ' . basename($file));
    if (is_file($file)) {
        require_once $file;
    }
}

$hasher = new App\Domains\WorkCore\System\Modules\Documents\Services\CanonicalPayloadHasher();
$assert(
    $hasher->hash(['b' => 2, 'a' => ['z' => 3, 'y' => 1]])
        === $hasher->hash(['a' => ['y' => 1, 'z' => 3], 'b' => 2]),
    'Canonical evidence hashes ignore associative key order.',
);

$signoff = new App\Domains\WorkCore\System\Modules\Documents\Services\EvidenceSignoffPolicy();
$assert($signoff->isSignable(['status' => 'active', 'checksum_sha256' => str_repeat('a', 64)]), 'Checksummed evidence is signable.');
$assert(! $signoff->isSignable(['status' => 'void', 'checksum_sha256' => str_repeat('a', 64)]), 'Void evidence is not signable.');

$scoring = new App\Domains\WorkCore\System\Modules\Assurance\Services\InspectionScoringPolicy();
$result = $scoring->score([
    ['result' => 'pass', 'weight' => 3, 'severity' => 'medium'],
    ['result' => 'fail', 'weight' => 1, 'severity' => 'high'],
    ['result' => 'na', 'weight' => 20, 'severity' => 'critical'],
]);
$assert($result['score'] === 75.0 && $result['outcome'] === 'conditional', 'Inspection scoring excludes N/A items and reports conditional failures.');

$critical = $scoring->score([
    ['result' => 'pass', 'weight' => 4, 'severity' => 'low'],
    ['result' => 'fail', 'weight' => 1, 'severity' => 'critical'],
]);
$assert($critical['outcome'] === 'fail' && $critical['critical_failures'] === 1, 'Critical inspection failures force a failed outcome.');

$matrix = new App\Domains\WorkCore\System\Modules\Assurance\Services\RiskMatrix();
$assert($matrix->assess(1, 1) === ['score' => 1, 'level' => 'low'], 'Risk matrix classifies low risk.');
$assert($matrix->assess(5, 5) === ['score' => 25, 'level' => 'critical'], 'Risk matrix classifies critical risk.');
try {
    $matrix->assess(0, 5);
    $assert(false, 'Risk matrix rejects values outside 1–5.');
} catch (InvalidArgumentException) {
    $assert(true, 'Risk matrix rejects values outside 1–5.');
}

$trust = App\Domains\WorkCore\System\Modules\TrustAccounting\Domain\TrustLedgerPolicy::class;
$assert($trust::mayPostReceipt(false, 'trust'), 'Trust receipts may post only to a trust account.');
$assert(! $trust::mayPostReceipt(true, 'trust'), 'Operating accounts cannot receive trust receipts.');
$assert($trust::mayReleaseDisbursement(2, 2), 'Trust disbursement releases after all approvals.');
$assert(! $trust::mayReleaseDisbursement(1, 2), 'Trust disbursement remains blocked below the approval threshold.');
$assert($trust::correctionMode() === 'reversal_and_replacement', 'Trust ledger corrections preserve an immutable audit trail.');

if ($failures !== []) {
    fwrite(STDERR, "WorkCore extraction policy tests FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    fwrite(STDERR, sprintf("%d passed; %d failed.\n", $passes, count($failures)));
    exit(1);
}

echo sprintf("WorkCore extraction policy tests passed: %d checks.\n", $passes);

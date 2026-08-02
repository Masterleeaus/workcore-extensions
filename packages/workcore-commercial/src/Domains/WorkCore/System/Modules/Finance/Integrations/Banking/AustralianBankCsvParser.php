<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\DecimalMoneyParser;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final class AustralianBankCsvParser
{
    public function __construct(private readonly DecimalMoneyParser $moneyParser, private readonly int $maxRows = 10000) {}

    public function parse(string $csv, string $currencyCode): BankImportParseResult
    {
        $lines = preg_split('/\r\n|\n|\r/', ltrim($csv, "\xEF\xBB\xBF")) ?: [];
        while ($lines !== [] && trim((string) $lines[count($lines) - 1]) === '') array_pop($lines);
        if (count($lines) < 2) throw new InvalidArgumentException('Bank CSV requires a header and at least one row.');
        if (count($lines) - 1 > $this->maxRows) throw new InvalidArgumentException('Bank CSV exceeds the configured row limit.');
        $headers = array_map([$this, 'normaliseHeader'], str_getcsv((string) array_shift($lines)));
        $rows = []; $errors = [];
        foreach ($lines as $offset => $line) {
            if (trim((string) $line) === '') continue;
            $sourceRow = $offset + 2;
            try {
                $values = str_getcsv((string) $line);
                if (count($values) > count($headers)) throw new InvalidArgumentException('Row has more columns than the header.');
                $values = array_pad($values, count($headers), '');
                $data = array_combine($headers, $values);
                if (! is_array($data)) throw new InvalidArgumentException('Unable to map bank CSV row.');
                $date = $this->parseDate($this->first($data, ['date','transaction_date','value_date']));
                $description = trim($this->first($data, ['description','details','narrative','transaction_description'], ''));
                $reference = trim($this->first($data, ['reference','reference_text','transaction_reference'], ''));
                $payer = trim($this->first($data, ['payer','payer_payee','payer_payee_hint','name'], '')) ?: null;
                $amount = $this->parseAmount($data, $currencyCode);
                $hash = hash('sha256', implode('|', [$date->format('Y-m-d'), (string) $amount->minorAmount, strtoupper($currencyCode), $description, $reference, $payer ?? '']));
                $rows[] = new BankImportRow($sourceRow, $date, $description, $reference, $payer, $amount, $hash);
            } catch (\Throwable $e) {
                $errors[] = ['row' => $sourceRow, 'message' => $e->getMessage()];
            }
        }
        return new BankImportParseResult($rows, $errors);
    }

    private function normaliseHeader(string $header): string
    {
        $header = strtolower(trim($header));
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $header), '_');
    }

    /** @param array<string,string> $data @param list<string> $keys */
    private function first(array $data, array $keys, ?string $default = null): string
    {
        foreach ($keys as $key) if (isset($data[$key]) && trim((string) $data[$key]) !== '') return trim((string) $data[$key]);
        if ($default !== null) return $default;
        throw new InvalidArgumentException('Required bank CSV column is missing or blank.');
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        foreach (['!d/m/Y','!d-m-Y','!Y-m-d','!d/m/y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, trim($value));
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return $date;
        }
        throw new InvalidArgumentException('Bank transaction date is not a supported Australian date.');
    }

    /** @param array<string,string> $data */
    private function parseAmount(array $data, string $currency): Money
    {
        $credit = $this->first($data, ['credit','credit_amount'], '');
        $debit = $this->first($data, ['debit','debit_amount'], '');
        if ($credit !== '') return $this->moneyParser->parse($this->cleanDecimal($credit), $currency)->absolute();
        if ($debit !== '') return $this->moneyParser->parse($this->cleanDecimal($debit), $currency)->absolute()->negate();
        $amountText = $this->first($data, ['amount','transaction_amount']);
        $amount = $this->moneyParser->parse($this->cleanDecimal($amountText), $currency);
        $type = strtoupper($this->first($data, ['type','dr_cr','credit_debit'], ''));
        if (in_array($type, ['DR','DEBIT','D'], true)) return $amount->absolute()->negate();
        if (in_array($type, ['CR','CREDIT','C'], true)) return $amount->absolute();
        return $amount;
    }

    private function cleanDecimal(string $value): string
    {
        $value = trim(str_replace([',','$',' '], '', $value));
        if (preg_match('/^\((.+)\)$/', $value, $m) === 1) $value = '-' . $m[1];
        if ($value === '-' || $value === '') throw new InvalidArgumentException('Bank transaction amount is blank.');
        return $value;
    }
}

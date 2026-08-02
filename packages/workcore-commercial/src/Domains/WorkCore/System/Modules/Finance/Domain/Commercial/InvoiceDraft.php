<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final readonly class InvoiceDraft
{
    /** @param list<InvoiceLineDraft> $lines @param array<string,mixed> $customerSnapshot @param array<string,mixed> $sellerSnapshot */
    public function __construct(
        public string $id,
        public string $companyId,
        public string $customerId,
        public ?string $siteId,
        public ?string $jobId,
        public string $sourceType,
        public string $sourceId,
        public string $sourceKey,
        public string $invoiceNumber,
        public DateTimeImmutable $issueDate,
        public DateTimeImmutable $dueDate,
        public Money $subtotal,
        public Money $discount,
        public Money $tax,
        public Money $total,
        public array $lines,
        public bool $pricesIncludeTax,
        public array $customerSnapshot,
        public array $sellerSnapshot,
    ) {
        foreach ([$id, $companyId, $customerId, $sourceType, $sourceId, $sourceKey, $invoiceNumber] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Invoice identity values must not be blank.');
            }
        }
        if ($dueDate < $issueDate) {
            throw new InvalidArgumentException('Invoice due date cannot be before its issue date.');
        }
        if ($lines === []) {
            throw new InvalidArgumentException('Invoice requires at least one line.');
        }
        foreach ($lines as $line) {
            if (! $line instanceof InvoiceLineDraft) {
                throw new InvalidArgumentException('Every invoice line must be an InvoiceLineDraft.');
            }
        }
        $currency = $subtotal->currency;
        foreach ([$discount, $tax, $total] as $amount) {
            if (! $currency->equals($amount->currency)) {
                throw new DomainException('Invoice totals must use one currency.');
            }
        }
        if (! $subtotal->subtract($discount)->add($tax)->equals($total)) {
            throw new DomainException('Invoice totals do not balance.');
        }
    }

    public function currencyCode(): string
    {
        return $this->total->currency->value;
    }

    /** @return array<string,mixed> */
    public function snapshotPayload(): array
    {
        return [
            'invoice_id' => $this->id,
            'invoice_number' => $this->invoiceNumber,
            'company_id' => $this->companyId,
            'customer_id' => $this->customerId,
            'site_id' => $this->siteId,
            'job_id' => $this->jobId,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'issue_date' => $this->issueDate->format('Y-m-d'),
            'due_date' => $this->dueDate->format('Y-m-d'),
            'currency_code' => $this->currencyCode(),
            'prices_include_tax' => $this->pricesIncludeTax,
            'subtotal' => $this->subtotal->toArray(),
            'discount' => $this->discount->toArray(),
            'tax' => $this->tax->toArray(),
            'total' => $this->total->toArray(),
            'customer_snapshot' => $this->customerSnapshot,
            'seller_snapshot' => $this->sellerSnapshot,
            'lines' => array_map(static fn (InvoiceLineDraft $line): array => $line->toArray(), $this->lines),
        ];
    }
}

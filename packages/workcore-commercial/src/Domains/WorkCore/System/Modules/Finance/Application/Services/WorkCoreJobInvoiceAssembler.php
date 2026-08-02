<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Services;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\DocumentNumberGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceDraft;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceLineDraft;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Tax\GstCalculator;
use App\Domains\WorkCore\System\Modules\Finance\Numbering\SequenceKey;
use DateTimeImmutable;
use InvalidArgumentException;

final class WorkCoreJobInvoiceAssembler
{
    public function __construct(
        private readonly IdentifierGenerator $ids,
        private readonly DocumentNumberGenerator $numbers,
        private readonly GstCalculator $gst,
    ) {
    }

    /** @param array<string,mixed> $job @param list<array<string,mixed>> $sourceLines */
    public function assemble(string $companyId, array $job, array $sourceLines, DateTimeImmutable $now): InvoiceDraft
    {
        $jobId = $this->string($job, 'id');
        $customerId = $this->string($job, 'customer_id');
        $currency = strtoupper((string) ($job['currency_code'] ?? 'AUD'));
        $pricesIncludeTax = (bool) ($job['prices_include_tax'] ?? true);
        $terms = max(0, (int) ($job['payment_terms_days'] ?? 7));
        $issueDate = $now->setTime(0, 0);
        $dueDate = $issueDate->modify("+{$terms} days");

        $lines = [];
        $subtotal = Money::zero($currency);
        $discount = Money::zero($currency);
        $tax = Money::zero($currency);
        $total = Money::zero($currency);

        foreach (array_values($sourceLines) as $index => $source) {
            $quantityScaled = (int) ($source['quantity_scaled'] ?? 100);
            $quantityScale = (int) ($source['quantity_scale'] ?? 2);
            $unitPrice = Money::ofMinor((int) ($source['unit_price_minor'] ?? 0), $currency);
            $discountInput = Money::ofMinor((int) ($source['discount_minor'] ?? 0), $currency);
            $rate = (int) ($source['tax_rate_basis_points'] ?? 1000);
            $grossMinor = $this->multiplyScaled($unitPrice->minorAmount, $quantityScaled, $quantityScale);
            $gross = Money::ofMinor($grossMinor, $currency);
            if ($discountInput->compareTo($gross) > 0) {
                throw new InvalidArgumentException('Invoice line discount cannot exceed the gross line amount.');
            }

            if ($pricesIncludeTax) {
                $grossBreakdown = $this->gst->fromInclusive($gross, $rate);
                $discountBreakdown = $this->gst->fromInclusive($discountInput, $rate);
                $lineSubtotal = $grossBreakdown->subtotal;
                $lineDiscount = $discountBreakdown->subtotal;
                $lineTotal = $gross->subtract($discountInput);
                $lineTax = $lineTotal->subtract($lineSubtotal->subtract($lineDiscount));
            } else {
                $lineSubtotal = $gross;
                $lineDiscount = $discountInput;
                $taxable = $gross->subtract($discountInput);
                $lineTax = $this->gst->fromExclusive($taxable, $rate)->tax;
                $lineTotal = $taxable->add($lineTax);
            }

            $line = new InvoiceLineDraft(
                id: $this->ids->uuid(),
                position: $index + 1,
                description: $this->string($source, 'description'),
                quantityScaled: $quantityScaled,
                quantityScale: $quantityScale,
                unitPrice: $unitPrice,
                subtotal: $lineSubtotal,
                discount: $lineDiscount,
                tax: $lineTax,
                total: $lineTotal,
                sourceType: isset($source['source_type']) ? (string) $source['source_type'] : 'workcore_job_line',
                sourceId: isset($source['source_id']) ? (string) $source['source_id'] : null,
                taxCode: isset($source['tax_code']) ? (string) $source['tax_code'] : ($rate > 0 ? 'GST' : 'GST_FREE'),
                taxRateBasisPoints: $rate,
                metadata: is_array($source['metadata'] ?? null) ? $source['metadata'] : [],
            );
            $lines[] = $line;
            $subtotal = $subtotal->add($lineSubtotal);
            $discount = $discount->add($lineDiscount);
            $tax = $tax->add($lineTax);
            $total = $total->add($lineTotal);
        }

        $invoiceId = $this->ids->uuid();
        return new InvoiceDraft(
            id: $invoiceId,
            companyId: $companyId,
            customerId: $customerId,
            siteId: isset($job['site_id']) ? (string) $job['site_id'] : null,
            jobId: $jobId,
            sourceType: 'workcore_job',
            sourceId: $jobId,
            sourceKey: 'workcore_job:' . $jobId,
            invoiceNumber: $this->numbers->next($companyId, SequenceKey::Invoice, $issueDate),
            issueDate: $issueDate,
            dueDate: $dueDate,
            subtotal: $subtotal,
            discount: $discount,
            tax: $tax,
            total: $total,
            lines: $lines,
            pricesIncludeTax: $pricesIncludeTax,
            customerSnapshot: is_array($job['customer_snapshot'] ?? null) ? $job['customer_snapshot'] : ['id' => $customerId],
            sellerSnapshot: is_array($job['seller_snapshot'] ?? null) ? $job['seller_snapshot'] : ['company_id' => $companyId],
        );
    }

    private function multiplyScaled(int $minorAmount, int $quantityScaled, int $scale): int
    {
        if ($minorAmount < 0 || $quantityScaled <= 0 || $scale < 0 || $scale > 6) {
            throw new InvalidArgumentException('Invalid invoice quantity or unit price.');
        }
        $divisor = 10 ** $scale;
        return intdiv(($minorAmount * $quantityScaled) + intdiv($divisor, 2), $divisor);
    }

    /** @param array<string,mixed> $values */
    private function string(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("WorkCore commercial snapshot requires {$key}.");
        }
        return trim($value);
    }
}

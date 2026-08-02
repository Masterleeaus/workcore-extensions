<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition, BusinessActionRegistry};
use App\Domains\WorkCore\System\Modules\TrustAccounting\Actions\{
    AllocateTrustReceipt, ApproveTrustDisbursement, CreateTrustAccount, CreateTrustMatter,
    RecordTrustReceipt, ReleaseTrustDisbursement, RequestTrustDisbursement, ReverseTrustLedgerEntry
};
use App\Domains\WorkCore\System\Modules\TrustAccounting\Contracts\TrustAccountingRepositoryContract;
use App\Domains\WorkCore\System\Modules\TrustAccounting\ReadModels\{GetTrustMatterLedger, GetTrustSummary};
use App\Domains\WorkCore\System\Modules\TrustAccounting\Repositories\EloquentTrustAccountingRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition, ReadModelRegistry};
use Illuminate\Support\ServiceProvider;

final class WorkTrustAccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TrustAccountingRepositoryContract::class, EloquentTrustAccountingRepository::class);
        $actions = $this->app->make(BusinessActionRegistry::class);
        $definitions = [
            ['workcore.trust.account.create', CreateTrustAccount::class, 'critical', 'manage_accounts'],
            ['workcore.trust.matter.create', CreateTrustMatter::class, 'high', 'manage_matters'],
            ['workcore.trust.receipt.record', RecordTrustReceipt::class, 'critical', 'record_receipts'],
            ['workcore.trust.receipt.allocate', AllocateTrustReceipt::class, 'critical', 'allocate_receipts'],
            ['workcore.trust.disbursement.request', RequestTrustDisbursement::class, 'critical', 'request_disbursements'],
            ['workcore.trust.disbursement.approve', ApproveTrustDisbursement::class, 'critical', 'approve_disbursements'],
            ['workcore.trust.disbursement.release', ReleaseTrustDisbursement::class, 'critical', 'release_disbursements'],
            ['workcore.trust.ledger.reverse', ReverseTrustLedgerEntry::class, 'critical', 'reverse_ledger'],
        ];
        foreach ($definitions as [$key, $handler, $risk, $permission]) {
            $actions->register(new ActionDefinition(
                key: $key,
                handler: $handler,
                risk: $risk,
                requiresConfirmation: true,
                capability: 'workcore.trust_accounting',
                permission: (string) config("workcore.trust_accounting.permissions.{$permission}"),
                metadata: ['domain' => 'regulated_client_money', 'canonical_owner' => 'WorkCore Trust Accounting'],
            ));
        }
        $reads = $this->app->make(ReadModelRegistry::class);
        $view = (string) config('workcore.trust_accounting.permissions.view');
        $reads->register(new ReadModelDefinition('workcore.trust.summary', GetTrustSummary::class, 'workcore.trust_accounting', permission: $view));
        $reads->register(new ReadModelDefinition('workcore.trust.matter.ledger', GetTrustMatterLedger::class, 'workcore.trust_accounting', permission: $view));
    }
}

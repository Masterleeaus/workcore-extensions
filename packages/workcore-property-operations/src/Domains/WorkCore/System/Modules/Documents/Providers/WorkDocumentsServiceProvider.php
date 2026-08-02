<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Documents\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition, BusinessActionRegistry};
use App\Domains\WorkCore\System\Modules\Documents\Actions\{
    AddDocumentComment,
    AddDocumentVersion,
    CreateDocument,
    DecideDocumentApproval,
    LinkDocument,
    RegisterEvidence,
    RequestDocumentApproval,
    SearchDocuments,
    SearchEvidence,
    SignoffEvidence
};
use App\Domains\WorkCore\System\Modules\Documents\Contracts\DocumentRepositoryContract;
use App\Domains\WorkCore\System\Modules\Documents\Repositories\EloquentDocumentRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition, ReadModelRegistry};
use Illuminate\Support\ServiceProvider;

final class WorkDocumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentRepositoryContract::class, EloquentDocumentRepository::class);

        $actions = $this->app->make(BusinessActionRegistry::class);
        $definitions = [
            ['workcore.document.create', CreateDocument::class, 'medium', 'create'],
            ['workcore.document.version.add', AddDocumentVersion::class, 'medium', 'version'],
            ['workcore.document.link', LinkDocument::class, 'low', 'link'],
            ['workcore.document.comment.add', AddDocumentComment::class, 'low', 'comment'],
            ['workcore.document.approval.request', RequestDocumentApproval::class, 'medium', 'approve'],
            ['workcore.document.approval.decide', DecideDocumentApproval::class, 'high', 'approve'],
            ['workcore.evidence.register', RegisterEvidence::class, 'high', 'evidence'],
            ['workcore.evidence.signoff', SignoffEvidence::class, 'high', 'signoff'],
        ];
        foreach ($definitions as [$key, $handler, $risk, $permission]) {
            $actions->register(new ActionDefinition(
                key: $key,
                handler: $handler,
                risk: $risk,
                requiresConfirmation: true,
                capability: 'workcore.documents',
                permission: (string) config("workcore.documents.permissions.{$permission}"),
                metadata: ['domain' => 'documents_evidence', 'canonical_owner' => 'WorkCore Documents'],
            ));
        }

        $reads = $this->app->make(ReadModelRegistry::class);
        $view = (string) config('workcore.documents.permissions.view');
        $reads->register(new ReadModelDefinition('workcore.document.search', SearchDocuments::class, 'workcore.documents', permission: $view));
        $reads->register(new ReadModelDefinition('workcore.evidence.search', SearchEvidence::class, 'workcore.documents', permission: $view));
    }

    public function boot(): void
    {
        if ((bool) config('workcore.documents.routes_enabled', false) && is_file(__DIR__ . '/../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}

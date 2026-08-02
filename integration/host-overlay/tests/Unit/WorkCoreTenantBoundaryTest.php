<?php

use App\Domains\WorkCore\Actions\BusinessActionDispatcher;
use App\Domains\WorkCore\Contracts\AuthorizesBusinessActions;
use App\Domains\WorkCore\Contracts\BusinessAction;
use App\Domains\WorkCore\Data\ActionContext;
use Illuminate\Validation\ValidationException;

it('rejects tenant and actor fields supplied inside an action payload', function () {
    $action = new class implements BusinessAction {
        public function name(): string { return 'test.action'; }
        public function execute(array $payload, ActionContext $context): array { return ['data' => $payload]; }
    };
    $authorizer = new class implements AuthorizesBusinessActions {
        public function authorize(string $action, ActionContext $context): void {}
    };

    $dispatcher = new BusinessActionDispatcher([$action], $authorizer);
    $context = new ActionContext(companyId: 'company-a', actorId: 42, correlationId: 'corr-1');

    expect(fn () => $dispatcher->dispatch('test.action', [
        'company_id' => 'company-b',
        'created_by' => 'attacker',
    ], $context))->toThrow(ValidationException::class);
});

it('produces tenancy attributes exclusively from trusted context', function () {
    $context = new ActionContext(
        companyId: 'company-a',
        actorId: 42,
        correlationId: 'corr-1',
        branchId: 'branch-a',
        workspaceId: 'workspace-a',
    );

    expect($context->tenancyAttributes())->toBe([
        'company_id' => 'company-a',
        'branch_id' => 'branch-a',
        'workspace_id' => 'workspace-a',
        'created_by' => '42',
    ]);
});

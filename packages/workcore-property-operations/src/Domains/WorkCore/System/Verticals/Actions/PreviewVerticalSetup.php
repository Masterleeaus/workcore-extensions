<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Verticals\Setup\{VerticalSetupPlanCompiler,VerticalSetupQuestionBank};

final class PreviewVerticalSetup implements BusinessActionHandlerContract
{
    public function __construct(
        private VerticalSetupPlanCompiler $compiler,
        private VerticalSetupQuestionBank $questions,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $primary = (string) $request->payload['primary_vertical'];
        $addOns = array_values(array_map('strval', (array) ($request->payload['add_ons'] ?? [])));
        $answers = (array) ($request->payload['answers'] ?? []);
        $plan = $this->compiler->compile($primary, $addOns, $answers);

        return new ActionHandlerResult([
            'questions' => $this->questions->questions($primary, $addOns, $answers),
            'plan' => $plan->toArray(),
            'requires_human_review' => true,
        ]);
    }
}

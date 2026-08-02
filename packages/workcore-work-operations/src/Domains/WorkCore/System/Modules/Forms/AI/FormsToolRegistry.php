<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Forms\AI;
use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};
final class FormsToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        return [
            T::read('forms_search_templates','Search company form and checklist templates.','workcore.form_template.search',T::object(['filters'=>T::freeObject(),'perPage'=>T::integer(1,100)])),
            T::read('forms_search_submissions','Search authorised form submissions.','workcore.form_submission.search',T::object(['filters'=>T::freeObject(),'perPage'=>T::integer(1,100)]),'restricted'),
        ];
    }
}

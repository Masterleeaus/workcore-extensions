<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Wizards\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};

final class WizardsToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id=T::string(26);
        return [
            T::read('wizards_list_definitions', 'List available WorkCore guided setup and operational wizards.', 'workcore.wizard.list', T::object([
                'filters'=>T::freeObject(),
            ])),
            T::read('wizards_get_run', 'Get a company-scoped wizard run and its current state.', 'workcore.wizard.run', T::object([
                'runPublicId'=>$id,
            ], ['runPublicId'])),
            T::read('wizards_next_question', 'Get the next applicable question and progress for a wizard run.', 'workcore.wizard.next_question', T::object([
                'runPublicId'=>$id,
            ], ['runPublicId'])),
            T::action('wizards_start_run', 'Start a WorkCore wizard run.', 'workcore.wizard_run.start', T::object([
                'definition_key'=>T::string(120),'context'=>T::freeObject(),
            ], ['definition_key'], true)),
            T::action('wizards_save_answer', 'Save an AI-assisted wizard answer with provenance and confirmation state.', 'workcore.wizard_answer.save', T::object([
                'run_public_id'=>$id,'question_key'=>T::string(160),'value'=>[],
                'source'=>T::string(40, ['user_entered','ai_suggested','imported','derived']),
                'confidence'=>T::number(0,1),'is_confirmed'=>T::boolean(),'agent_public_id'=>$id,
            ], ['run_public_id','question_key','value'], true)),
        ];
    }
}

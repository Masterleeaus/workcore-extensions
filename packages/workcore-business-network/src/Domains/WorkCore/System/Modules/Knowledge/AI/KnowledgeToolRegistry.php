<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Knowledge\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};

final class KnowledgeToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id = T::string(26);
        return [
            T::read('knowledge_search_articles', 'Search company knowledge articles for grounded internal answers.', 'workcore.knowledge.article.search', T::object([
                'filters' => T::freeObject(), 'perPage' => T::integer(1,100),
            ])),
            T::action('knowledge_create_article', 'Create a draft knowledge article through WorkCore governance.', 'workcore.knowledge.article.create', T::object([
                'category_public_id' => $id, 'title' => T::string(255), 'slug' => T::string(255),
                'summary' => T::string(2000), 'content' => T::string(50000), 'status' => T::string(30),
                'visibility' => T::string(30), 'tags' => T::stringArray(50), 'metadata' => T::freeObject(),
            ], ['title','content'])),
            T::action('knowledge_update_article', 'Update an existing knowledge article through WorkCore governance.', 'workcore.knowledge.article.update', T::object([
                'knowledge_article_public_id' => $id, 'category_public_id' => $id, 'title' => T::string(255),
                'summary' => T::string(2000), 'content' => T::string(50000), 'visibility' => T::string(30),
                'tags' => T::stringArray(50), 'metadata' => T::freeObject(),
            ], ['knowledge_article_public_id'], true)),
            T::action('knowledge_change_article_status', 'Change a knowledge article publication status.', 'workcore.knowledge.article.change_status', T::object([
                'knowledge_article_public_id' => $id, 'status' => T::string(30, ['draft','review','published','archived']),
            ], ['knowledge_article_public_id','status'])),
        ];
    }
}

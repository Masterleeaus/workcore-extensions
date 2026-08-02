<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class KnowledgeArticleResource
{
    public static function make(array|object $row): array{$record=(array)$row;foreach(['audiences','metadata'] as $field)if(isset($record[$field])&&is_string($record[$field]))$record[$field]=json_decode($record[$field],true);return ['id'=>$record['public_id']??null,'type'=>'knowledge_article','attributes'=>array_diff_key($record,['public_id'=>true,'company_id'=>true,'id'=>true])];}
}

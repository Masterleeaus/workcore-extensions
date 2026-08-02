<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class SupportTicketResource
{
    public static function make(array|object $row): array
    {
        $record=(array)$row;
        foreach(['metadata','attachments'] as $field){if(isset($record[$field])&&is_string($record[$field]))$record[$field]=json_decode($record[$field],true);}
        if(isset($record['messages'])&&is_array($record['messages']))foreach($record['messages'] as &$message){if(isset($message['attachments'])&&is_string($message['attachments']))$message['attachments']=json_decode($message['attachments'],true);}
        return ['id'=>$record['public_id']??null,'type'=>'support_ticket','attributes'=>array_diff_key($record,['public_id'=>true,'company_id'=>true,'id'=>true])];
    }
}

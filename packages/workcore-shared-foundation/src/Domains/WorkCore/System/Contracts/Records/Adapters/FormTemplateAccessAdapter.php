<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\FormTemplateAccessContract;
final class FormTemplateAccessAdapter extends AbstractDatabaseRecordAccess implements FormTemplateAccessContract
{
    protected function table(): string { return 'tz_form_templates'; }
    protected function type(): string { return 'form_template'; }
    protected function columns(): array { return ['public_id','code','name','form_type','version','status','applies_to','description','settings','active','published_at','created_at','updated_at']; }
}

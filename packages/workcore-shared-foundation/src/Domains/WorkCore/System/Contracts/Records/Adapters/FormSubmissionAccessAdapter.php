<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\FormSubmissionAccessContract;
final class FormSubmissionAccessAdapter extends AbstractDatabaseRecordAccess implements FormSubmissionAccessContract
{
    protected function table(): string { return 'tz_form_submissions'; }
    protected function type(): string { return 'form_submission'; }
    protected function columns(): array { return ['public_id','template_version','subject_type','subject_public_id','status','score','outcome','started_at','submitted_at','approved_at','signature_name','signature_path','notes','metadata','created_at','updated_at']; }
}

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreLeaveRequest extends FormRequest { public function authorize(): bool{return true;} public function rules(): array{return ['worker_public_id'=>['required','string','size:26'],'leave_type_public_id'=>['required','string','size:26'],'replacement_worker_public_id'=>['nullable','string','size:26'],'starts_at'=>['required','date'],'ends_at'=>['required','date','after:starts_at'],'timezone'=>['nullable','timezone'],'status'=>['sometimes','in:draft,submitted'],'reason'=>['nullable','string'],'notes'=>['nullable','string'],'document_path'=>['nullable','string','max:500'],'affects_roster'=>['sometimes','boolean']];} }

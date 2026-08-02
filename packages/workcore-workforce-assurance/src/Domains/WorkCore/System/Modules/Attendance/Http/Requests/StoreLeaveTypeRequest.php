<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreLeaveTypeRequest extends FormRequest { public function authorize(): bool{return true;} public function rules(): array{return ['code'=>['required','string','max:60'],'name'=>['required','string','max:160'],'paid'=>['sometimes','boolean'],'unit'=>['sometimes','in:minutes,hours,days'],'requires_document'=>['sometimes','boolean'],'max_consecutive_days'=>['nullable','integer','min:1'],'active'=>['sometimes','boolean'],'settings'=>['nullable','array']];} }

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ClockOutRequest extends FormRequest { public function authorize(): bool{return true;} public function rules(): array{return ['clocked_out_at'=>['nullable','date'],'latitude'=>['nullable','numeric','between:-90,90'],'longitude'=>['nullable','numeric','between:-180,180'],'notes'=>['nullable','string']];} }

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class DecideTimesheetRequest extends FormRequest { public function authorize(): bool{return true;} public function rules(): array{return ['status'=>['required','in:approved,rejected,locked'],'reason'=>['nullable','string']];} }

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class SubmitTimesheetRequest extends FormRequest { public function authorize(): bool{return true;} public function rules(): array{return ['worker_public_id'=>['required','string','size:26'],'period_starts_on'=>['required','date'],'period_ends_on'=>['required','date','after_or_equal:period_starts_on']];} }

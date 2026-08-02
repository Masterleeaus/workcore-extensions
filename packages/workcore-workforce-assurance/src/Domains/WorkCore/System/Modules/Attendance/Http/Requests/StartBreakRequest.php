<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StartBreakRequest extends FormRequest { public function authorize(): bool{return true;} public function rules(): array{return ['break_type'=>['sometimes','in:meal,rest,travel,personal,other'],'started_at'=>['nullable','date'],'paid'=>['sometimes','boolean'],'reason'=>['nullable','string']];} }

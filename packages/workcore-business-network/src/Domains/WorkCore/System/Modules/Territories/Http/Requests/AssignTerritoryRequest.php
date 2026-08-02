<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Territories\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class AssignTerritoryRequest extends FormRequest
{ public function authorize(): bool{return true;} public function rules(): array{return ['assignable_type'=>['required','in:worker,premises,customer,service,asset,roster,stock_location'],'assignable_public_id'=>['required','string','size:26'],'assignment_role'=>['nullable','string','max:50'],'priority'=>['nullable','integer','min:1','max:10000'],'effective_from'=>['nullable','date'],'effective_until'=>['nullable','date','after_or_equal:effective_from'],'active'=>['nullable','boolean'],'metadata'=>['nullable','array']];} }

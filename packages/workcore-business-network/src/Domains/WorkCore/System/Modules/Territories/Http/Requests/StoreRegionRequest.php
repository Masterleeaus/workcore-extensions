<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Territories\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreRegionRequest extends FormRequest
{ public function authorize(): bool{return true;} public function rules(): array{return ['code'=>['required','string','max:100'],'name'=>['required','string','max:200'],'description'=>['nullable','string','max:5000'],'manager_worker_public_id'=>['nullable','string','size:26'],'timezone'=>['nullable','timezone'],'status'=>['nullable','in:active,inactive,archived'],'metadata'=>['nullable','array']];} }

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Territories\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreBranchRequest extends FormRequest
{ public function authorize(): bool{return true;} public function rules(): array{return ['district_public_id'=>['required','string','size:26'],'premises_public_id'=>['nullable','string','size:26'],'manager_worker_public_id'=>['nullable','string','size:26'],'code'=>['required','string','max:100'],'name'=>['required','string','max:200'],'description'=>['nullable','string','max:5000'],'phone'=>['nullable','string','max:50'],'email'=>['nullable','email','max:200'],'status'=>['nullable','in:active,inactive,archived'],'metadata'=>['nullable','array']];} }

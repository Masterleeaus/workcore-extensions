<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreCertificationRequest extends FormRequest
{public function authorize():bool{return true;} public function rules():array{return ['skill_public_id'=>['nullable','string','size:26'],'name'=>['required','string','max:200'],'issuer'=>['nullable','string','max:200'],'credential_number'=>['nullable','string','max:150'],'issued_on'=>['nullable','date'],'expires_on'=>['nullable','date','after_or_equal:issued_on'],'status'=>['sometimes','in:valid,pending,expired,suspended,revoked'],'document_path'=>['nullable','string','max:500'],'notes'=>['nullable','string','max:5000']];}}

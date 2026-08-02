<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StartRepairOrderRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['assigned_worker_public_id'=>['nullable','string','size:26'],'started_at'=>['nullable','date'],'diagnosis'=>['nullable','string','max:10000']];}
}

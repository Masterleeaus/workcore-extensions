<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Rewind\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

abstract class RewindResourceModel extends Model
{
    use BelongsToCompany;
    public $timestamps = false;

    protected $guarded = ['id', 'company_id', 'public_id'];
}

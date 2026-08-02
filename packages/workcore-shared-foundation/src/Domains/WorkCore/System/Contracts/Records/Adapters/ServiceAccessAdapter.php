<?php

declare(strict_types=1); namespace App\Domains\WorkCore\System\Contracts\Records\Adapters; use App\Domains\WorkCore\System\Contracts\Records\ServiceAccessContract; final class ServiceAccessAdapter extends AbstractDatabaseRecordAccess implements ServiceAccessContract{protected function table():string{return 'tz_services';}protected function type():string{return 'service';}protected function columns():array{return ['public_id','category_id','code','name','description','default_duration_minutes','pricing_model','base_price','tax_code','status'];}}

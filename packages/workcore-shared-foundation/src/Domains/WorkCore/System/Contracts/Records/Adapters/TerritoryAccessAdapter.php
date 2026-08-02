<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\TerritoryAccessContract;
final class TerritoryAccessAdapter extends AbstractDatabaseRecordAccess implements TerritoryAccessContract
{ protected function table():string{return 'tz_territories';} protected function type():string{return 'territory';} protected function columns():array{return ['public_id','branch_id','code','name','description','territory_type','postcode_patterns','suburbs','state','country_code','centre_latitude','centre_longitude','radius_km','polygon_geojson','status','priority'];} }

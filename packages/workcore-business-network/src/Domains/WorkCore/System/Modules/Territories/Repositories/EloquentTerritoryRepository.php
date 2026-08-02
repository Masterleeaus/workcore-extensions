<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Repositories;

use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\Territories\Contracts\TerritoryRepositoryContract;
use App\Domains\WorkCore\System\Modules\Territories\Geometry\{GeoJsonCentroid, PolygonGeoJson};
use App\Domains\WorkCore\System\Modules\Territories\Services\TerritoryHierarchyPolicy;
use App\Domains\WorkCore\System\Modules\Territories\Services\TerritoryMatcher;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class EloquentTerritoryRepository extends TenantScopedRepository implements TerritoryRepositoryContract
{
    public function __construct(
        \Illuminate\Database\ConnectionInterface $db,
        \App\Domains\WorkCore\System\Contracts\TenantContextContract $tenant,
        private CompanyRecordAuthorizer $authorizer,
        private TerritoryHierarchyPolicy $policy,
        private TerritoryMatcher $matcher,
        private PolygonGeoJson $polygonGeoJson,
        private GeoJsonCentroid $geoJsonCentroid,
    ) { parent::__construct($db, $tenant); }

    public function createRegion(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $this->policy->assertStatus((string)($data['status'] ?? 'active'));
        $publicId=(string)Str::ulid();
        $this->db->table('tz_regions')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'manager_worker_id'=>$this->workerId($companyId,$data['manager_worker_public_id']??null),
            'code'=>strtoupper(trim((string)$data['code'])),'name'=>trim((string)$data['name']),'description'=>$data['description']??null,
            'timezone'=>$data['timezone']??config('workcore.context.default_timezone','Australia/Melbourne'),'status'=>$data['status']??'active',
            'metadata'=>$this->json($data['metadata']??null),'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->node('tz_regions',$companyId,$publicId,'region');
    }

    public function createDistrict(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $region=$this->record('tz_regions',$companyId,(string)$data['region_public_id']);
        $this->policy->assertParentCompany($companyId,(int)$region->company_id,'region');
        $publicId=(string)Str::ulid();
        $this->db->table('tz_districts')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'region_id'=>$region->id,'manager_worker_id'=>$this->workerId($companyId,$data['manager_worker_public_id']??null),
            'code'=>strtoupper(trim((string)$data['code'])),'name'=>trim((string)$data['name']),'description'=>$data['description']??null,
            'status'=>$data['status']??'active','metadata'=>$this->json($data['metadata']??null),'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->node('tz_districts',$companyId,$publicId,'district');
    }

    public function createBranch(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $district=$this->record('tz_districts',$companyId,(string)$data['district_public_id']);
        $this->policy->assertParentCompany($companyId,(int)$district->company_id,'district');
        $publicId=(string)Str::ulid();
        $this->db->table('tz_branches')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'district_id'=>$district->id,'manager_worker_id'=>$this->workerId($companyId,$data['manager_worker_public_id']??null),
            'premises_id'=>$this->premisesId($companyId,$data['premises_public_id']??null),'code'=>strtoupper(trim((string)$data['code'])),'name'=>trim((string)$data['name']),
            'description'=>$data['description']??null,'phone'=>$data['phone']??null,'email'=>$data['email']??null,'status'=>$data['status']??'active',
            'metadata'=>$this->json($data['metadata']??null),'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->node('tz_branches',$companyId,$publicId,'branch');
    }

    public function createTerritory(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $branch=$this->record('tz_branches',$companyId,(string)$data['branch_public_id']);
        $this->policy->assertParentCompany($companyId,(int)$branch->company_id,'branch'); $this->policy->assertStatus((string)($data['status']??'active'));
        $publicId=(string)Str::ulid();
        $polygon=$this->normalisePolygon($data['polygon_geojson']??null);
        $centre=$polygon!==null ? $this->geoJsonCentroid->centroid(json_decode($polygon,true,512,JSON_THROW_ON_ERROR)) : null;
        $this->db->table('tz_territories')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'branch_id'=>$branch->id,'code'=>strtoupper(trim((string)$data['code'])),'name'=>trim((string)$data['name']),
            'description'=>$data['description']??null,'territory_type'=>$data['territory_type']??'postcode','postcode_patterns'=>$this->json($data['postcode_patterns']??[]),
            'suburbs'=>$this->json($data['suburbs']??[]),'state'=>$data['state']??null,'country_code'=>strtoupper((string)($data['country_code']??'AU')),
            'centre_latitude'=>$data['centre_latitude']??$centre['latitude']??null,'centre_longitude'=>$data['centre_longitude']??$centre['longitude']??null,'radius_km'=>$data['radius_km']??null,
            'polygon_geojson'=>$polygon,'status'=>$data['status']??'active','priority'=>(int)($data['priority']??100),
            'metadata'=>$this->json($data['metadata']??null),'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->findByPublicId($companyId,$publicId) ?? ['public_id'=>$publicId];
    }

    public function assign(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $territory=$this->record('tz_territories',$companyId,(string)$data['territory_public_id']);
        $type=(string)$data['assignable_type']; $this->policy->assertAssignmentType($type);
        $this->assertAssignable($companyId,$type,(string)$data['assignable_public_id']);
        $publicId=(string)Str::ulid();
        $this->db->table('tz_territory_assignments')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'territory_id'=>$territory->id,'assignable_type'=>$type,'assignable_public_id'=>$data['assignable_public_id'],
            'assignment_role'=>$data['assignment_role']??'coverage','priority'=>(int)($data['priority']??100),'effective_from'=>$data['effective_from']??null,
            'effective_until'=>$data['effective_until']??null,'active'=>(bool)($data['active']??true),'metadata'=>$this->json($data['metadata']??null),
            'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return (array)$this->db->table('tz_territory_assignments')->where('company_id',$companyId)->where('public_id',$publicId)->first();
    }

    public function search(int $companyId, WorkCoreAccessLevel $level, int $actorId, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $q=$this->baseTerritoryQuery($companyId); $this->authorizer->apply($q,$level,$actorId,'territories.created_by_user_id',['territories.created_by_user_id']);
        $q->when($filters['q']??null,static fn($x,$v)=>$x->where(static fn($n)=>$n->where('territories.name','like',"%{$v}%")->orWhere('territories.code','like',"%{$v}%")));
        foreach (['status'=>'territories.status','branch_id'=>'branches.public_id','district_id'=>'districts.public_id','region_id'=>'regions.public_id'] as $key=>$column) {
            $q->when($filters[$key]??null,static fn($x,$v)=>$x->where($column,$v));
        }
        return $q->orderBy('territories.priority')->orderBy('territories.name')->paginate(max(1,min($perPage,100)));
    }

    public function match(int $companyId, ?string $postcode, ?string $suburb, ?float $latitude, ?float $longitude, int $limit = 20): array
    {
        $this->assertCompany($companyId);
        $rows=$this->baseTerritoryQuery($companyId)->where('territories.status','active')->get()->map(fn($r)=>(array)$r)->all();
        foreach ($rows as &$row) { $row['match_score']=$this->matcher->score($row,$postcode,$suburb,$latitude,$longitude); }
        unset($row); $rows=array_values(array_filter($rows,static fn($r)=>(int)$r['match_score']>0));
        usort($rows,static fn($a,$b)=>[(int)$b['match_score'],(int)$a['priority']] <=> [(int)$a['match_score'],(int)$b['priority']]);
        return array_slice($rows,0,max(1,min($limit,100)));
    }

    public function findByPublicId(int $companyId, string $publicId): ?array
    {
        $this->assertCompany($companyId); $row=$this->baseTerritoryQuery($companyId)->where('territories.public_id',$publicId)->first();
        return $row ? (array)$row : null;
    }

    private function baseTerritoryQuery(int $companyId): \Illuminate\Database\Query\Builder
    {
        return $this->db->table('tz_territories as territories')
            ->join('tz_branches as branches',fn($j)=>$j->on('branches.id','=','territories.branch_id')->on('branches.company_id','=','territories.company_id'))
            ->join('tz_districts as districts',fn($j)=>$j->on('districts.id','=','branches.district_id')->on('districts.company_id','=','territories.company_id'))
            ->join('tz_regions as regions',fn($j)=>$j->on('regions.id','=','districts.region_id')->on('regions.company_id','=','territories.company_id'))
            ->where('territories.company_id',$companyId)->whereNull('territories.deleted_at')->whereNull('branches.deleted_at')->whereNull('districts.deleted_at')->whereNull('regions.deleted_at')
            ->select(['territories.public_id','territories.code','territories.name','territories.description','territories.territory_type','territories.postcode_patterns','territories.suburbs','territories.state','territories.country_code','territories.centre_latitude','territories.centre_longitude','territories.radius_km','territories.polygon_geojson','territories.status','territories.priority','branches.public_id as branch_public_id','branches.name as branch_name','districts.public_id as district_public_id','districts.name as district_name','regions.public_id as region_public_id','regions.name as region_name']);
    }

    private function node(string $table,int $companyId,string $publicId,string $type): array
    { $row=$this->db->table($table)->where('company_id',$companyId)->where('public_id',$publicId)->first(); $data=$row?(array)$row:['public_id'=>$publicId]; $data['record_type']=$type; return $data; }
    private function record(string $table,int $companyId,string $publicId): object
    { $row=$this->db->table($table)->where('company_id',$companyId)->where('public_id',$publicId)->whereNull('deleted_at')->first(); if(!$row){throw new \InvalidArgumentException('Referenced territory hierarchy record does not belong to the active company.');} return $row; }
    private function workerId(int $companyId,mixed $publicId): ?int
    { if(!$publicId)return null; $r=$this->record('tz_workers',$companyId,(string)$publicId); return (int)$r->id; }
    private function premisesId(int $companyId,mixed $publicId): ?int
    { if(!$publicId)return null; $r=$this->record('tz_premises',$companyId,(string)$publicId); return (int)$r->id; }
    private function assertAssignable(int $companyId,string $type,string $publicId): void
    {
        $map=['worker'=>'tz_workers','premises'=>'tz_premises','customer'=>'tz_customers','service'=>'tz_services','asset'=>'tz_assets','roster'=>'tz_rosters','stock_location'=>'tz_stock_locations'];
        $table=$map[$type]??null; if(!$table||!$this->db->table($table)->where('company_id',$companyId)->where('public_id',$publicId)->exists()) throw new \InvalidArgumentException('Territory assignment target does not belong to the active company.');
    }
    private function guard(int $companyId,int $actorId): void
    { $this->assertCompany($companyId); if($actorId!==$this->actorId())throw new \RuntimeException('Repository actor does not match the active WorkCore actor.'); }
    private function normalisePolygon(mixed $value): ?string
    { return is_string($value)&&trim($value)!==''?$this->polygonGeoJson->normalise($value):null; }
    private function json(mixed $value): ?string
    { return $value===null?null:json_encode($value,JSON_THROW_ON_ERROR); }
}

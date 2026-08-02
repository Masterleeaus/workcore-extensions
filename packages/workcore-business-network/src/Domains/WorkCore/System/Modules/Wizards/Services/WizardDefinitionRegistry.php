<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Services;
use Illuminate\Database\ConnectionInterface;use InvalidArgumentException;
final class WizardDefinitionRegistry
{
    private array $system=[];
    public function __construct(private ConnectionInterface $db){foreach((array)require __DIR__.'/../config/definitions.php' as $d)$this->system[(string)$d['key']]=$d;}
    public function all(?int $companyId=null):array{$all=$this->system;if($companyId&&$this->hasTable()){foreach($this->db->table('tz_wizard_definitions')->where('company_id',$companyId)->where('status','published')->orderByDesc('version')->get() as $row){$d=json_decode((string)$row->definition,true,512,JSON_THROW_ON_ERROR);$all[(string)$d['key']]=$d;}}return array_values($all);}
    public function get(string $key,?int $companyId=null):array{if($companyId&&$this->hasTable()){$row=$this->db->table('tz_wizard_definitions')->where('company_id',$companyId)->where('definition_key',$key)->where('status','published')->orderByDesc('version')->first();if($row)return json_decode((string)$row->definition,true,512,JSON_THROW_ON_ERROR);}return $this->system[$key]??throw new InvalidArgumentException("Wizard definition [{$key}] is not registered.");}
    public function has(string $key,?int $companyId=null):bool{try{$this->get($key,$companyId);return true;}catch(InvalidArgumentException){return false;}}
    private function hasTable():bool{return $this->db->getSchemaBuilder()->hasTable('tz_wizard_definitions');}
}

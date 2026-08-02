<?php

declare(strict_types=1);
$root=dirname(__DIR__,2);$checks=0;
$assert=static function(bool $ok,string $message)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($message);};
$required=[
'app/Domains/WorkCore/System/Modules/Finance/Repositories/EloquentFinanceRepository.php',
'app/Domains/WorkCore/System/Modules/Finance/Contracts/PaymentOrchestrationRepositoryContract.php',
'app/Domains/WorkCore/System/Modules/Finance/Repositories/EloquentPaymentOrchestrationRepository.php',
'app/Domains/WorkCore/System/Modules/Finance/Infrastructure/Persistence/DatabasePaymentMethodConfigurationRepository.php',
'app/Domains/WorkCore/Database/Migrations/2026_08_02_000001_create_tm_credit_note_runtime_tables.php',
'app/Domains/WorkCore/Database/Migrations/2026_08_02_000002_create_tm_payment_orchestration_tables.php',
];foreach($required as $file)$assert(is_file($root.'/'.$file),'Missing '.$file);
$provider=file_get_contents($root.'/app/Domains/WorkCore/System/Modules/Finance/WorkCoreFinanceServiceProvider.php');
foreach(['workcore.finance.quote.create','workcore.finance.invoice.issue','workcore.finance.journal.post','workcore.payment.provider.upsert','workcore.payment.session.create','workcore.payment.attempt.record','workcore.payment.orchestration.summary'] as $key)$assert(str_contains($provider,$key),'Provider missing '.$key);
$migrations='';foreach(glob($root.'/app/Domains/WorkCore/Database/Migrations/*.php') as $file)$migrations.=file_get_contents($file)."\n";
foreach(['tm_credit_note_lines','tm_credit_note_allocations','tm_payment_provider_connections','tm_payment_sessions','tm_payment_attempts'] as $table)$assert(substr_count($migrations,"Schema::create('{$table}'")===1,"{$table} must have one owner");
$assert(!str_contains($migrations,"Schema::create('tz_finance_"),'Parallel tz_finance schema found.');$assert(!str_contains($migrations,"Schema::create('tz_payment_"),'Parallel tz_payment schema found.');
$owners=[];foreach(glob($root.'/app/Domains/WorkCore/Database/Migrations/*.php') as $file){$src=file_get_contents($file);preg_match_all("/Schema::create\\(['\"]([^'\"]+)['\"]/",$src,$m);foreach($m[1] as $table)$owners[$table][]=basename($file);}foreach($owners as $table=>$files)$assert(count($files)===1,"Duplicate table owner {$table}: ".implode(',',$files));
echo "Finance completion architecture passed: {$checks} checks.\n";

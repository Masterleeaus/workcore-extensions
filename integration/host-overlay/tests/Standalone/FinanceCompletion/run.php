<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
foreach ([
    'app/Domains/WorkCore/System/References/TypedReference.php',
    'app/Domains/WorkCore/System/Actions/PendingDomainEvent.php',
    'app/Domains/WorkCore/System/Actions/ActionRequest.php',
    'app/Domains/WorkCore/System/Actions/ActionHandlerResult.php',
    'app/Domains/WorkCore/System/Actions/Contracts/BusinessActionHandlerContract.php',
    'app/Domains/WorkCore/System/Modules/Finance/Contracts/FinanceRepositoryContract.php',
    'app/Domains/WorkCore/System/Modules/Finance/Contracts/PaymentOrchestrationRepositoryContract.php',
    'app/Domains/WorkCore/System/Modules/Finance/Actions/CreateFinanceQuote.php',
    'app/Domains/WorkCore/System/Modules/Finance/Actions/ChangeFinanceInvoiceStatus.php',
    'app/Domains/WorkCore/System/Modules/Finance/Actions/AllocateFinanceCredit.php',
    'app/Domains/WorkCore/System/Modules/Finance/Actions/UpsertPaymentProviderConnection.php',
    'app/Domains/WorkCore/System/Modules/Finance/Actions/CreatePaymentSession.php',
    'app/Domains/WorkCore/System/Modules/Finance/Actions/RecordPaymentAttempt.php',
] as $file) require_once $root.'/'.$file;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Modules\Finance\Actions\{AllocateFinanceCredit, ChangeFinanceInvoiceStatus, CreateFinanceQuote, CreatePaymentSession, RecordPaymentAttempt, UpsertPaymentProviderConnection};
use App\Domains\WorkCore\System\Modules\Finance\Contracts\{FinanceRepositoryContract, PaymentOrchestrationRepositoryContract};

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void { $checks++; if (! $condition) throw new RuntimeException($message); };

$finance = new class implements FinanceRepositoryContract {
    public array $last=[];
    public function create(array $d):string{return 'invoice-1';} public function findById(string $id):?array{return null;} public function update(string $id,array $d):bool{return true;}
    public function createQuote(array $d,int $c,int $a):array{$this->last=func_get_args();return ['id'=>'quote-1'];}
    public function transitionQuote(string $id,string $s,array $d,int $c,int $a):array{return ['id'=>$id,'state'=>$s];}
    public function createInvoice(array $d,int $c,int $a):array{return ['id'=>'invoice-1'];}
    public function issueInvoice(string $id,array $d,int $c,int $a):array{return ['id'=>$id];}
    public function transitionInvoice(string $id,string $s,array $d,int $c,int $a):array{$this->last=func_get_args();return ['id'=>$id,'state'=>$s];}
    public function createCreditNote(array $d,int $c,int $a):array{return ['id'=>'credit-1'];}
    public function allocateCredit(string $credit,string $invoice,array $d,int $c,int $a):array{$this->last=func_get_args();return ['allocation'=>['id'=>'allocation-1']];}
    public function recordExpense(array $d,int $c,int $a):array{return ['id'=>'expense-1'];} public function approveExpense(string $id,array $d,int $c,int $a):array{return ['id'=>$id];}
    public function allocateReceivable(string $id,array $d,int $c,int $a):array{return ['allocation'=>['id'=>'allocation-2']];}
    public function createAccount(array $d,int $c,int $a):array{return ['id'=>'account-1'];} public function createAccountingPeriod(array $d,int $c,int $a):array{return ['id'=>'period-1'];} public function closeAccountingPeriod(string $id,array $d,int $c,int $a):array{return ['id'=>$id];} public function postJournal(array $d,int $c,int $a):array{return ['id'=>'journal-1'];}
    public function quoteProfile(string $id,int $c):array{return ['id'=>$id];} public function invoiceProfile(string $id,int $c):array{return ['id'=>$id];} public function financeSummary(int $c):array{return [];} public function searchReceivables(int $c,array $f,int $p=25):mixed{return [];}
};
$req = static fn(string $key,array $payload) => new ActionRequest($key,$payload,7,9,'test-'.$key);
$r=(new CreateFinanceQuote($finance))->handle($req('workcore.finance.quote.create',['workcore_customer_id'=>'c','lines'=>[['description'=>'x','unit_price_minor'=>100]]]));
$assert($r->aggregate?->type==='finance_quote' && $r->aggregate?->id==='quote-1','Quote action aggregate failed.');
$assert($r->events[0]->name==='workcore.finance.quote.created','Quote event failed.');
$r=(new ChangeFinanceInvoiceStatus($finance))->handle($req('workcore.finance.invoice.transition',['invoice_id'=>'invoice-9','state'=>'approved']));
$assert($finance->last[0]==='invoice-9' && $finance->last[1]==='approved','Invoice transition arguments failed.');
$assert($r->aggregate?->id==='invoice-9','Invoice transition aggregate failed.');
$r=(new AllocateFinanceCredit($finance))->handle($req('workcore.finance.credit.allocate',['credit_note_id'=>'credit-9','invoice_id'=>'invoice-9']));
$assert($finance->last[0]==='credit-9' && $finance->last[1]==='invoice-9','Credit allocation arguments failed.');
$assert($r->aggregate?->id==='allocation-1','Credit allocation aggregate failed.');

$payments = new class implements PaymentOrchestrationRepositoryContract {
    public array $last=[];
    public function upsertProviderConnection(array $d,int $c,int $a):array{$this->last=func_get_args();return ['id'=>'connection-1'];}
    public function createSession(array $d,int $c,int $a):array{$this->last=func_get_args();return ['id'=>'session-1','session_token'=>'once'];}
    public function recordAttempt(array $d,int $c,int $a):array{$this->last=func_get_args();return ['id'=>'attempt-1'];}
    public function paymentSession(string $id,int $c):array{return ['id'=>$id];} public function summary(int $c):array{return [];}
};
$r=(new UpsertPaymentProviderConnection($payments))->handle($req('workcore.payment.provider.upsert',['payment_method'=>'payid','provider_key'=>'manual']));
$assert($r->aggregate?->type==='payment_provider_connection' && $r->aggregate?->id==='connection-1','Provider action failed.');
$assert($r->events[0]->name==='workcore.payment.provider.configured','Provider event failed.');
$r=(new CreatePaymentSession($payments))->handle($req('workcore.payment.session.create',['payment_method'=>'payid','amount_minor'=>100,'currency_code'=>'AUD']));
$assert($r->aggregate?->type==='payment_session' && $r->aggregate?->id==='session-1','Session action failed.');
$r=(new RecordPaymentAttempt($payments))->handle($req('workcore.payment.attempt.record',['session_id'=>'session-1']));
$assert($r->aggregate?->type==='payment_attempt' && $r->aggregate?->id==='attempt-1','Attempt action failed.');
$assert($payments->last[0]['session_id']==='session-1','Attempt session forwarding failed.');
$thrown=false; try {(new RecordPaymentAttempt($payments))->handle($req('workcore.payment.attempt.record',[]));} catch (InvalidArgumentException) {$thrown=true;}
$assert($thrown,'Missing session must fail closed.');
echo "Finance completion behaviour passed: {$checks} checks.\n";

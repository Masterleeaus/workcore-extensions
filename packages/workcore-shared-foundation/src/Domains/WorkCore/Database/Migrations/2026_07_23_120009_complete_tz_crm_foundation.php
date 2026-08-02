<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_lead_sources', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('name'); $table->string('key'); $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id', 'key']);
        });
        Schema::create('tz_lead_statuses', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('name'); $table->string('key'); $table->boolean('is_closed')->default(false);
            $table->boolean('is_won')->default(false); $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps(); $table->softDeletes(); $table->unique(['company_id', 'key']);
        });
        Schema::create('tz_lead_activities', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('tz_leads')->cascadeOnDelete();
            $table->string('type'); $table->json('payload')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->index(['company_id', 'lead_id', 'created_at']);
        });
        Schema::table('tz_customers', function (Blueprint $table): void {
            $table->string('normalized_email')->nullable()->after('email');
            $table->string('normalized_phone', 50)->nullable()->after('phone');
            $table->string('status')->default('active')->after('notes');
            $table->index(['company_id', 'normalized_email']); $table->index(['company_id', 'normalized_phone']);
        });
        Schema::table('tz_customer_contacts', function (Blueprint $table): void {
            $table->string('normalized_email')->nullable()->after('email');
            $table->string('normalized_phone', 50)->nullable()->after('phone');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('tz_leads', function (Blueprint $table): void {
            $table->foreign('source_id')->references('id')->on('tz_lead_sources')->nullOnDelete();
            $table->foreign('status_id')->references('id')->on('tz_lead_statuses')->nullOnDelete();
            $table->string('normalized_email')->nullable()->after('email');
            $table->string('normalized_phone', 50)->nullable()->after('phone');
            $table->timestamp('follow_up_at')->nullable()->after('next_follow_up');
            $table->foreignId('converted_customer_id')->nullable()->constrained('tz_customers')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->index(['company_id', 'status_id']); $table->index(['company_id', 'source_id']);
            $table->index(['company_id', 'follow_up_at']);
        });
        DB::table('tz_companies')->select('id')->orderBy('id')->each(function (object $company): void {
            $now = now();
            foreach ([['web','Website',10],['phone','Phone',20],['referral','Referral',30],['chat','Chat',40]] as [$key,$name,$sort]) {
                DB::table('tz_lead_sources')->insertOrIgnore(['public_id'=>(string) Str::ulid(),'company_id'=>$company->id,'key'=>$key,'name'=>$name,'sort_order'=>$sort,'created_at'=>$now,'updated_at'=>$now]);
            }
            foreach ([['new','New',false,false,10],['contacted','Contacted',false,false,20],['qualified','Qualified',false,false,30],['won','Won',true,true,90],['lost','Lost',true,false,100]] as [$key,$name,$closed,$won,$sort]) {
                DB::table('tz_lead_statuses')->insertOrIgnore(['public_id'=>(string) Str::ulid(),'company_id'=>$company->id,'key'=>$key,'name'=>$name,'is_closed'=>$closed,'is_won'=>$won,'sort_order'=>$sort,'created_at'=>$now,'updated_at'=>$now]);
            }
        });
    }
    public function down(): void
    {
        Schema::table('tz_leads', function (Blueprint $table): void { $table->dropForeign(['source_id']); $table->dropForeign(['status_id']); $table->dropConstrainedForeignId('converted_customer_id'); $table->dropColumn(['normalized_email','normalized_phone','follow_up_at','converted_at']); });
        Schema::table('tz_customer_contacts', function (Blueprint $table): void { $table->dropConstrainedForeignId('created_by_user_id'); $table->dropConstrainedForeignId('updated_by_user_id'); $table->dropColumn(['normalized_email','normalized_phone']); });
        Schema::table('tz_customers', function (Blueprint $table): void { $table->dropColumn(['normalized_email','normalized_phone','status']); });
        Schema::dropIfExists('tz_lead_activities'); Schema::dropIfExists('tz_lead_statuses'); Schema::dropIfExists('tz_lead_sources');
    }
};

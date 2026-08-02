<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\RecurringServices\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreServiceAgreementRequest extends FormRequest { public function rules(): array{return [
'customer_public_id'=>'nullable|string','premises_public_id'=>'nullable|string','service_public_id'=>'required|string','job_template_public_id'=>'nullable|string','source_rebooking_request_public_id'=>'nullable|string',
'agreement_number'=>'nullable|string|max:64','name'=>'required|string|max:255','agreement_type'=>'nullable|in:service,maintenance,inspection,cleaning,compliance,management,turnover,property_care','status'=>'nullable|in:draft,active',
'starts_on'=>'required|date','ends_on'=>'nullable|date|after_or_equal:starts_on','renewal_type'=>'nullable|in:none,manual,automatic','renewal_notice_days'=>'nullable|integer|min:0|max:365',
'timezone'=>'nullable|timezone','auto_create_work_orders'=>'nullable|boolean','auto_create_appointments'=>'nullable|boolean','generation_lead_days'=>'nullable|integer|min:0|max:365','default_priority'=>'nullable|in:low,normal,high,urgent,emergency','default_duration_minutes'=>'nullable|integer|min:1|max:10080','access_notes'=>'nullable|string|max:10000','notes'=>'nullable|string|max:20000','metadata'=>'nullable|array',
'rules'=>'required|array|min:1','rules.*.frequency'=>'required|in:daily,weekly,monthly,quarterly,yearly','rules.*.interval_count'=>'nullable|integer|min:1|max:365','rules.*.weekdays'=>'nullable|array','rules.*.weekdays.*'=>'integer|min:1|max:7','rules.*.day_of_month'=>'nullable|integer|min:1|max:31','rules.*.month_of_year'=>'nullable|integer|min:1|max:12','rules.*.time_of_day'=>'nullable|date_format:H:i','rules.*.duration_minutes'=>'nullable|integer|min:1|max:10080','rules.*.timezone'=>'nullable|timezone','rules.*.starts_on'=>'nullable|date','rules.*.ends_on'=>'nullable|date','rules.*.occurrence_limit'=>'nullable|integer|min:1','rules.*.metadata'=>'nullable|array'];}}

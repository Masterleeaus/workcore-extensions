<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Console\Commands;

use App\Domains\WorkCore\System\Company\Actions\CreateCompany;
use App\Domains\WorkCore\System\Company\DTOs\CreateCompanyData;
use App\Domains\WorkCore\System\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

final class WorkCoreBootstrapCompanyCommand extends Command
{
    protected $signature = 'workcore:bootstrap-company
        {--user= : Existing MagicAI user ID that will own the company}
        {--name= : Company name}
        {--slug= : Company slug; generated from the name when omitted}
        {--timezone=Australia/Sydney : IANA timezone}
        {--currency=AUD : ISO 4217 currency code}
        {--country=AU : ISO 3166-1 alpha-2 country code}
        {--abn= : Optional Australian Business Number}
        {--gst : Mark the company as GST registered}';

    protected $description = 'Create the first WorkCore company and owner membership for an existing MagicAI user.';

    public function handle(CreateCompany $createCompany): int
    {
        $userId = filter_var($this->option('user'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $name = trim((string) $this->option('name'));

        if ($userId === false || $name === '') {
            $this->error('Both --user=<id> and --name="Company Name" are required.');
            return self::INVALID;
        }

        $userModel = (string) config('workcore.identity.user_model', 'App\\Models\\User');
        if ($userModel === '' || ! class_exists($userModel) || ! is_subclass_of($userModel, Model::class)) {
            $this->error("Configured WorkCore user model [{$userModel}] is not a loadable Eloquent model.");
            return self::FAILURE;
        }

        if (! $userModel::query()->whereKey((int) $userId)->exists()) {
            $this->error("MagicAI user [{$userId}] does not exist.");
            return self::FAILURE;
        }

        $slug = trim((string) $this->option('slug'));
        $slug = $slug !== '' ? Str::slug($slug) : Str::slug($name);
        if ($slug === '') {
            $this->error('The company slug could not be generated. Supply --slug explicitly.');
            return self::INVALID;
        }

        if (Company::query()->where('slug', $slug)->exists()) {
            $this->error("A WorkCore company with slug [{$slug}] already exists.");
            return self::FAILURE;
        }

        try {
            $company = $createCompany->execute(new CreateCompanyData(
                ownerUserId: (int) $userId,
                name: $name,
                slug: $slug,
                timezone: trim((string) $this->option('timezone')) ?: 'Australia/Sydney',
                currencyCode: strtoupper(trim((string) $this->option('currency')) ?: 'AUD'),
                countryCode: strtoupper(trim((string) $this->option('country')) ?: 'AU'),
                abn: ($abn = trim((string) $this->option('abn'))) !== '' ? $abn : null,
                gstRegistered: (bool) $this->option('gst'),
            ));
        } catch (Throwable $exception) {
            $this->error('Unable to create the WorkCore company: ' . $exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Created WorkCore company [{$company->getKey()}] {$company->name} ({$company->slug}).");
        return self::SUCCESS;
    }
}

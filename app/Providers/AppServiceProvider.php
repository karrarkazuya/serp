<?php

namespace App\Providers;

use App\Models\Contacts\Contact;
use App\Models\Security\Permission;
use App\Models\Security\Role;
use App\Models\Settings\Company;
use App\Models\Settings\Setting;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Policies\CompanyPolicy;
use App\Policies\ContactPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingPolicy;
use App\Policies\UserPolicy;
use App\Services\Chatter\ChatterService;
use App\Services\Company\CompanyContextService;
use App\Services\Company\CompanyService;
use App\Services\Contacts\ContactService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatterService::class);
        $this->app->singleton(ContactService::class);
        $this->app->singleton(CompanyContextService::class);
        $this->app->singleton(CompanyService::class);
    }

    public function boot(): void
    {
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);

        foreach ([
            User::class,
            Company::class,
            Contact::class,
            \App\Models\Contacts\Tag::class,
            Role::class,
            Permission::class,
            Setting::class,
            \App\Models\Chatter\ChatterMessage::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }

        // Share company context with all views for the navbar switcher
        View::composer('components.navbar', function ($view) {
            if (auth()->check()) {
                $context  = app(CompanyContextService::class);
                $allowed  = auth()->user()->companies()->where('active', true)->orderBy('name')->get();
                $activeIds = $context->getActiveCompanyIds();

                $view->with([
                    'allowedCompanies' => $allowed,
                    'activeCompanyIds' => $activeIds,
                    'companyLabel'     => $context->getLabel(),
                ]);
            }
        });
    }
}

<?php

namespace Lalalili\SurveyFilament\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lalalili\PackageTestingSupport\PackageTestCase;
use Lalalili\AudienceCore\AudienceCoreServiceProvider;
use Lalalili\SurveyCore\SurveyCoreServiceProvider;
use Lalalili\SurveyFilament\SurveyFilamentServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;

abstract class TestCase extends PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AudienceCoreServiceProvider::class,
            ActivitylogServiceProvider::class,
            SurveyCoreServiceProvider::class,
            SurveyFilamentServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        // audience_lists / audience_list_rows 的 canonical migration 在 audience-core；
        // survey-core 曾有一份重複的（未註冊）副本，移除後這裡必須明確載入。
        $this->loadMigrationsFrom(__DIR__.'/../../audience-core/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../survey-core/database/migrations');
    }
}

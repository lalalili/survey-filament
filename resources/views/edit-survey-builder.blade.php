<x-filament-panels::page :full-height="true">
    <style>
        .fi-main-ctn { display: flex; flex-direction: column; height: 100dvh; min-height: unset !important; }
        .fi-main { flex: 1; min-height: 0; padding-left: 0 !important; padding-right: 0 !important; }
        .fi-page { height: 100%; }
        .fi-page-header-main-ctn { flex: 1; min-height: 0; padding-top: 0 !important; padding-bottom: 0 !important; gap: 0 !important; }
        .fi-page-main, .fi-page-content { height: 100%; min-height: 0; }
        .fi-page-content { gap: 0 !important; }
    </style>

    <div
        id="survey-builder-app"
        data-survey-id="{{ $survey->getKey() }}"
        data-endpoint-show="{{ $builderEndpoints['show'] }}"
        data-endpoint-update="{{ $builderEndpoints['update'] }}"
        data-endpoint-publish="{{ $builderEndpoints['publish'] }}"
        data-endpoint-upload-image="{{ $builderEndpoints['upload_image'] }}"
        data-csrf-token="{{ csrf_token() }}"
        data-turnstile-configured="{{ $turnstileConfigured ? '1' : '0' }}"
        data-language-setting-enabled="{{ $languageSettingEnabled ? '1' : '0' }}"
        data-thank-you-redirect-enabled="{{ $thankYouRedirectEnabled ? '1' : '0' }}"
        data-accent-color-setting-enabled="{{ $accentColorSettingEnabled ? '1' : '0' }}"
    ></div>

    @vite('packages/survey-filament/resources/js/builder/app.ts')
</x-filament-panels::page>

<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login as CustomLogin;
use App\Http\Middleware\LoadContext;
use App\Http\Middleware\RateLimitLogin;
use App\Http\Middleware\SetPanelLocale;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')

            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->passwordReset()
            ->login(CustomLogin::class)
            ->colors([
                'primary' => Color::Amber,
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->navigationGroups([

                NavigationGroup::make()
                    ->label('Credenciales')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Reportes')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Docentes')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Administrativos')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Alumnos')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Maestros')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Administración de Locales')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Administración de Procesos')
                    ->collapsed(false),

            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                RateLimitLogin::class,
                SetPanelLocale::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,

                LoadContext::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                BreezyCore::make()
                    ->myProfile(shouldRegisterUserMenu: true),
                FilamentBackgroundsPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,

            ])
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}

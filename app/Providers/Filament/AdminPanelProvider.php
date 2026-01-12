<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Resources\BarangKeluarResource;
use App\Filament\Resources\BarangMasukResource;
use App\Filament\Resources\PCInventoryResource;
use App\Filament\Resources\NonPCInventoryResource;
use App\Filament\Resources\SoftwareInventoryResource;
use App\Filament\Resources\ProdiResource;
use App\Filament\Resources\LecturerResource;
use App\Filament\Resources\CourseResource;
use App\Filament\Pages\ScheduleTimetable;
use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\KalenderAkademikWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\WelcomeWidget;
use App\Models\Laboratorium;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->spa()
            ->login()
            ->colors([
                'primary' => '#104b8f',
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('3s')
            ->favicon(url('images/udinus.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class, // Menggunakan Dashboard kustom kita
            ])
            ->widgets([
                WelcomeWidget::class,
                StatsOverviewWidget::class,
                KalenderAkademikWidget::class,
                CalendarWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])

            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                // Hanya lanjutkan jika pengguna sudah login
                if (!auth()->check()) {
                    return $builder;
                }

                $user = auth()->user();
                $navigationGroups = [];

                // Menu Utama (Dashboard) - selalu tampilkan untuk semua user
                $navigationGroups[] = NavigationGroup::make('Menu Utama')
                    ->items([
                        NavigationItem::make('Dashboard')
                            ->icon('heroicon-o-home')
                            ->url(fn() => Dashboard::getUrl())
                            ->isActiveWhen(fn() => request()->routeIs('filament.admin.pages.dashboard')),
                    ]);

                // PENJADWALAN - tampilkan untuk semua yang memiliki izin terkait penjadwalan
                $penjadwalanItems = [];

                // Program Studi
                if ($user->hasRole('super_admin') || $user->can('view-navigation-item', 'prodi')) {
                    $penjadwalanItems[] = NavigationItem::make('Program Studi')
                        ->icon('heroicon-o-academic-cap')
                        ->url(\App\Filament\Resources\ProdiResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\ProdiResource::getRouteBaseName() . '.*'));
                }

                // Dosen
                if ($user->hasRole('super_admin') || $user->can('view-navigation-item', 'lecturer')) {
                    $penjadwalanItems[] = NavigationItem::make('Dosen')
                        ->icon('heroicon-o-user-group')
                        ->url(\App\Filament\Resources\LecturerResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\LecturerResource::getRouteBaseName() . '.*'));
                }

                // Mata Kuliah
                if ($user->hasRole('super_admin') || $user->can('view-navigation-item', 'course')) {
                    $penjadwalanItems[] = NavigationItem::make('Mata Kuliah')
                        ->icon('heroicon-o-book-open')
                        ->url(\App\Filament\Resources\CourseResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\CourseResource::getRouteBaseName() . '.*'));
                }

                // Penjadwalan Otomatis (Schedule Wizard) - NEW!
                if ($user->hasRole('super_admin') || $user->can('page_ScheduleWizard')) {
                    $penjadwalanItems[] = NavigationItem::make('Penjadwalan Otomatis')
                        ->icon('heroicon-o-sparkles')
                        ->url('/admin/schedule-wizard')
                        ->isActiveWhen(fn() => request()->is('admin/schedule-wizard*'));
                }

                // Jadwal Kuliah (ScheduleResource) - untuk melihat/edit manual
                if ($user->hasRole('super_admin') || $user->can('view_any_schedule')) {
                    $penjadwalanItems[] = NavigationItem::make('Jadwal Kuliah')
                        ->icon('heroicon-o-calendar-days')
                        ->url(\App\Filament\Resources\ScheduleResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\ScheduleResource::getRouteBaseName() . '.*'));
                }

                // Timetable Visual (visualisasi jadwal)
                if ($user->hasRole('super_admin') || $user->can('page_ScheduleTimetable')) {
                    $penjadwalanItems[] = NavigationItem::make('Timetable Visual')
                        ->icon('heroicon-o-table-cells')
                        ->url(\App\Filament\Pages\ScheduleTimetable::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs('filament.admin.pages.schedule-timetable'));
                }

                // Tambahkan grup PENJADWALAN jika ada item di dalamnya
                if (count($penjadwalanItems) > 0) {
                    $navigationGroups[] = NavigationGroup::make('Penjadwalan')
                        ->items($penjadwalanItems);
                }

                // Pelaporan PTPP - tampilkan untuk semua yang memiliki izin terkait PTPP
                if ($user->hasRole('super_admin') || $user->can('view-navigation-item', 'lapor::ptpp')) {
                    $navigationGroups[] = NavigationGroup::make('Pelaporan PTPP')
                        ->items([
                            NavigationItem::make('PTTP SKT')
                                ->icon('heroicon-o-document-text')
                                ->url(\App\Filament\Resources\LaporPtppResource::getUrl())
                                ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\LaporPtppResource::getRouteBaseName() . '.*')),
                        ]);
                }

                // MASTER DATA - hanya tampilkan jika user memiliki izin
                $masterDataItems = [];

                // Data Laboran
                if ($user->can('view-navigation-item', 'user')) {
                    $masterDataItems[] = NavigationItem::make('Data Laboran')
                        ->icon('heroicon-o-users')
                        ->url(\App\Filament\Resources\UserResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\UserResource::getRouteBaseName() . '.*'));
                }

                // Data Laboratorium
                if ($user->can('view-navigation-item', 'laboratorium')) {
                    $masterDataItems[] = NavigationItem::make('Data Laboratorium')
                        ->icon('heroicon-o-building-office')
                        ->url(\App\Filament\Resources\LaboratoriumResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\LaboratoriumResource::getRouteBaseName() . '.*'));
                }

                // Data Klasifikasi Lab
                if ($user->can('view-navigation-item', 'klasifikasi::lab')) {
                    $masterDataItems[] = NavigationItem::make('Data Klasifikasi Lab')
                        ->icon('fluentui-dual-screen-desktop-24-o')
                        ->url(\App\Filament\Resources\KlasifikasiLabResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\KlasifikasiLabResource::getRouteBaseName() . '.*'));
                }

                // Permissions
                if ($user->hasRole('super_admin') || $user->can('view-navigation-item', 'role')) {
                    $masterDataItems[] = NavigationItem::make('Permissions')
                        ->icon('heroicon-o-shield-check')
                        ->url(fn() => route('filament.admin.resources.shield.roles.index'))
                        ->isActiveWhen(fn() => request()->routeIs('filament.admin.resources.shield.roles.*'));
                }

                // Tambahkan grup MASTER DATA jika ada item di dalamnya
                if (count($masterDataItems) > 0) {
                    $navigationGroups[] = NavigationGroup::make('MASTER DATA')
                        ->items($masterDataItems);
                }

                // DATA HARDWARE - hanya tampilkan jika user memiliki izin
                $hardwareItems = [];

                // Motherboard
                if ($user->can('view-navigation-item', 'motherboard')) {
                    $hardwareItems[] = NavigationItem::make('Motherboard')
                        ->icon('mdi-chip')
                        ->url(\App\Filament\Resources\MotherboardResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\MotherboardResource::getRouteBaseName() . '.*'));
                }

                // Processor
                if ($user->can('view-navigation-item', 'processor')) {
                    $hardwareItems[] = NavigationItem::make('Processor')
                        ->icon('heroicon-o-cpu-chip')
                        ->url(\App\Filament\Resources\ProcessorResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\ProcessorResource::getRouteBaseName() . '.*'));
                }

                // RAM
                if ($user->can('view-navigation-item', 'r::a::m')) {
                    $hardwareItems[] = NavigationItem::make('RAM')
                        ->icon('fluentui-ram-20')
                        ->url(\App\Filament\Resources\RAMResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\RAMResource::getRouteBaseName() . '.*'));
                }

                // VGA
                if ($user->can('view-navigation-item', 'v::g::a')) {
                    $hardwareItems[] = NavigationItem::make('VGA')
                        ->icon('clarity-box-plot-line')
                        ->url(\App\Filament\Resources\VGAResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\VGAResource::getRouteBaseName() . '.*'));
                }

                // Penyimpanan
                if ($user->can('view-navigation-item', 'penyimpanan')) {
                    $hardwareItems[] = NavigationItem::make('Penyimpanan')
                        ->icon('clarity-hard-disk-line')
                        ->url(\App\Filament\Resources\PenyimpananResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\PenyimpananResource::getRouteBaseName() . '.*'));
                }

                // DVD
                if ($user->can('view-navigation-item', 'd::v::d')) {
                    $hardwareItems[] = NavigationItem::make('DVD')
                        ->icon('clarity-cd-dvd-line')
                        ->url(\App\Filament\Resources\DVDResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\DVDResource::getRouteBaseName() . '.*'));
                }

                // PSU
                if ($user->can('view-navigation-item', 'p::s::u')) {
                    $hardwareItems[] = NavigationItem::make('PSU')
                        ->icon('mdi-cube')
                        ->url(\App\Filament\Resources\PSUResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\PSUResource::getRouteBaseName() . '.*'));
                }

                // Keyboard
                if ($user->can('view-navigation-item', 'keyboard')) {
                    $hardwareItems[] = NavigationItem::make('Keyboard')
                        ->icon('clarity-keyboard-line')
                        ->url(\App\Filament\Resources\KeyboardResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\KeyboardResource::getRouteBaseName() . '.*'));
                }

                // Mouse
                if ($user->can('view-navigation-item', 'mouse')) {
                    $hardwareItems[] = NavigationItem::make('Mouse')
                        ->icon('clarity-mouse-line')
                        ->url(\App\Filament\Resources\MouseResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\MouseResource::getRouteBaseName() . '.*'));
                }

                // Monitor
                if ($user->can('view-navigation-item', 'monitor')) {
                    $hardwareItems[] = NavigationItem::make('Monitor')
                        ->icon('mdi-monitor-small')
                        ->url(\App\Filament\Resources\MonitorResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\MonitorResource::getRouteBaseName() . '.*'));
                }

                // Headphone
                if ($user->can('view-navigation-item', 'headphone')) {
                    $hardwareItems[] = NavigationItem::make('Headphone')
                        ->icon('fluentui-headphones-24')
                        ->url(\App\Filament\Resources\HeadphoneResource::getUrl())
                        ->isActiveWhen(fn() => request()->routeIs(\App\Filament\Resources\HeadphoneResource::getRouteBaseName() . '.*'));
                }

                // Tambahkan grup Data Hardware jika ada item di dalamnya
                if (count($hardwareItems) > 0) {
                    $navigationGroups[] = NavigationGroup::make('Data Hardware')
                        ->items($hardwareItems);
                }

                // NAVIGASI LABORATORIUM
                // Ambil semua lab dari database untuk membuat navigasi dinamis
                $laboratories = Laboratorium::query()->orderBy('ruang')->get();

                foreach ($laboratories as $lab) {
                    // Gunakan hasLabPermission dari trait untuk pengecekan izin yang lebih akurat
                    if ($user->hasLabPermission($lab->id, 'view')) {
                        $labItems = [];

                        // Inventaris PC
                        $labItems[] = NavigationItem::make('Inventaris PC')
                            ->icon('heroicon-o-computer-desktop')
                            ->url(fn() => PCInventoryResource::getUrl('index', ['tableFilters[laboratorium][value]' => $lab->id]))
                            ->isActiveWhen(fn() => request()->routeIs(PCInventoryResource::getRouteBaseName() . '.index') && request()->input('tableFilters.laboratorium.value') == $lab->id);

                        // Inventaris Non-PC
                        $labItems[] = NavigationItem::make('Inventaris Non-PC')
                            ->icon('heroicon-o-cpu-chip')
                            ->url(fn() => NonPCInventoryResource::getUrl('index', ['tableFilters[laboratorium][value]' => $lab->id]))
                            ->isActiveWhen(fn() => request()->routeIs(NonPCInventoryResource::getRouteBaseName() . '.index') && request()->input('tableFilters.laboratorium.value') == $lab->id);

                        // Inventaris Software
                        $labItems[] = NavigationItem::make('Inventaris Software')
                            ->icon('heroicon-o-code-bracket-square')
                            ->url(fn() => SoftwareInventoryResource::getUrl('index', ['tableFilters[laboratorium][value]' => $lab->id]))
                            ->isActiveWhen(fn() => request()->routeIs(SoftwareInventoryResource::getRouteBaseName() . '.index') && request()->input('tableFilters.laboratorium.value') == $lab->id);

                        // Barang Masuk
                        $labItems[] = NavigationItem::make('Barang Masuk')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->url(fn() => BarangMasukResource::getUrl('index', ['tableFilters[laboratorium][value]' => $lab->id]))
                            ->isActiveWhen(fn() => request()->routeIs(BarangMasukResource::getRouteBaseName() . '.index') && request()->input('tableFilters.laboratorium.value') == $lab->id);

                        // Barang Keluar
                        $labItems[] = NavigationItem::make('Barang Keluar')
                            ->icon('heroicon-o-arrow-up-tray')
                            ->url(fn() => BarangKeluarResource::getUrl('index', ['tableFilters[laboratorium][value]' => $lab->id]))
                            ->isActiveWhen(fn() => request()->routeIs(BarangKeluarResource::getRouteBaseName() . '.index') && request()->input('tableFilters.laboratorium.value') == $lab->id);

                        $navigationGroups[] = NavigationGroup::make($lab->ruang)
                            ->items($labItems);
                    }
                }

                return $builder->groups($navigationGroups);
            });
    }
}

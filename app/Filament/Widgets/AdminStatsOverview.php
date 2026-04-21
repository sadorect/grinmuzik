<?php

namespace App\Filament\Widgets;

use App\Models\ArtistProfile;
use App\Models\Donation;
use App\Models\PageView;
use App\Models\Track;
use App\Models\User;
use App\Support\AdminPanelAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AdminPanelAccess::allows('view analytics');
    }

    protected function getStats(): array
    {
        $totalUsers     = User::count();
        $totalArtists   = ArtistProfile::where('is_active', true)->count();
        $onlineUsers    = User::query()->online()->count();
        $onlineArtists  = ArtistProfile::query()
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->online())
            ->count();
        $totalTracks    = Track::where('is_published', true)->count();
        $totalPlays     = Track::sum('play_count');
        $totalDonations = Donation::sum('amount');

        $totalPageViews    = PageView::count();
        $todayPageViews    = PageView::today()->count();
        $todayUniqueSessions = PageView::today()->distinct('session_id')->count('session_id');
        $totalUniqueSessions = PageView::distinct('session_id')->count('session_id');
        $resolvedLocations = PageView::resolved()->count();
        $topCountryToday = PageView::today()
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as aggregate')
            ->groupBy('country')
            ->orderByDesc('aggregate')
            ->value('country');

        return [
            Stat::make('Total Users', number_format($totalUsers))
                ->icon('heroicon-o-users'),

            Stat::make('Active Artists', number_format($totalArtists))
                ->icon('heroicon-o-microphone'),

            Stat::make('Users Online', number_format($onlineUsers))
                ->description('Active in the last 5 minutes')
                ->icon('heroicon-o-users'),

            Stat::make('Artists Online', number_format($onlineArtists))
                ->description('Active in the last 5 minutes')
                ->icon('heroicon-o-microphone'),

            Stat::make('Published Tracks', number_format($totalTracks))
                ->icon('heroicon-o-musical-note'),

            Stat::make('Total Plays', number_format($totalPlays))
                ->icon('heroicon-o-play'),

            Stat::make('Total Donations', '$' . number_format($totalDonations, 2))
                ->icon('heroicon-o-currency-dollar'),

            Stat::make('New Users Today', number_format(
                User::whereDate('created_at', today())->count()
            ))
                ->icon('heroicon-o-user-plus'),

            Stat::make('Total Page Views', number_format($totalPageViews))
                ->description(number_format($todayPageViews) . ' today')
                ->icon('heroicon-o-eye'),

            Stat::make('Unique Sessions', number_format($totalUniqueSessions))
                ->description(number_format($todayUniqueSessions) . ' today')
                ->icon('heroicon-o-user-circle'),

            Stat::make('Resolved Visitor Locations', number_format($resolvedLocations))
                ->description(($topCountryToday ?: 'No country yet') . ' leading today')
                ->icon('heroicon-o-map-pin'),
        ];
    }
}

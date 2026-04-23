<?php

namespace FluentCommunityPro\App\Services\Analytics;

use FluentCommunity\App\Models\XProfile;

class Members
{

    /**
     * @var $provider AnalyticsService
    */
    protected $provider;

    protected $startDate;

    protected $endDate;
    public function __construct($provider, $startDate, $endDate)
    {
        $this->provider = $provider;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function getMemberWidget()
    {
        $dateRanges = $this->getDateRanges($this->startDate, $this->endDate);
        $totalMembers = $this->provider->getWidgetCountsWithComparison($dateRanges, new XProfile(), __('Total Members', 'fluent-community-pro'));
        $activeMembers = $this->provider->getWidgetCountsWithComparison($dateRanges, new XProfile(), __('Active Members', 'fluent-community-pro'), 'last_activity');
        $newMembers = $this->provider->getWidgetCountsWithComparison($dateRanges, new XProfile(), __('New Members', 'fluent-community-pro'));
        $pendingMembers = $this->provider->getWidgetCountsWithComparison($dateRanges, (new XProfile())->where('status', 'pending'), __('Pending Members', 'fluent-community-pro'));

        $widgets = [
            'total_members'   => $totalMembers,
            'active_members'  => $activeMembers,
            'new_members'     => $newMembers,
            'pending_members' => $pendingMembers,
        ];

        return $widgets;
    }

    public function getActivity()
    {
        $chartStatistics = $this->provider->getChartStatistics($this->startDate, $this->endDate, 'members', 'created_at');
        $chartStatistics['title'] = __('Activity', 'fluent-community-pro');
        return $chartStatistics;
    }

    public function getTopMembers()
    {

        $dateRanges = $this->getDateRanges($this->startDate, $this->endDate);
        return XProfile::query()
            ->where('status', 'active')
            ->whereBetween('created_at', [$dateRanges['start_date'], $dateRanges['end_date']])
            ->orderBy('total_points', 'desc')
            ->take(10)
            ->get()
        ;
    }

    public function topPostStarter()
    {
        $dateRanges = $this->getDateRanges($this->startDate, $this->endDate);
        return XProfile::query()
            ->where('status', 'active')
            ->withCount([
                'posts' => function ($query) use ($dateRanges) {
                    return $query->whereBetween('created_at', [$dateRanges['start_date'], $dateRanges['end_date']]);
                }
            ])
            ->having('posts_count', '>', 0)
            ->orderBy('posts_count', 'desc')
            ->take(10)
            ->get()
        ;
    }

    public function topCommenters()
    {
        $dateRanges = $this->getDateRanges($this->startDate, $this->endDate);
        return XProfile::query()
            ->where('status', 'active')
            ->withCount(['comments' => function ($query) use ($dateRanges) {
                return $query->whereBetween('created_at', [$dateRanges['start_date'], $dateRanges['end_date']]);
            }])
            ->orderBy('comments_count', 'desc')
            ->having('comments_count', '>', 0)
            ->take(10)
            ->get()
        ;
    }

    public function getDateRanges($startDate, $endDate)
    {
        return [
            'start_date' => gmdate('Y-m-d', strtotime($startDate)) . ' 00:00:00',
            'end_date'   => gmdate('Y-m-d', strtotime($endDate)) . ' 23:59:59'
        ];
    }
}

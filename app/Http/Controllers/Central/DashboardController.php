<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The super-admin's landing page: how many workspaces exist and how fast they are
 * being added. Read-only.
 */
final class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => $this->stats(),
            'signups' => $this->signups(),
        ]);
    }

    /**
     * Aggregate stats over all live (non-archived) workspaces, in the app timezone.
     *
     * @return array{total: int, archived: int, added_today: int, last_7_days: int, newest: array{name: string, slug: string, created_at: string}|null}
     */
    private function stats(): array
    {
        $newest = Tenant::query()->latest()->first();

        return [
            'total' => Tenant::query()->count(),
            'archived' => Tenant::onlyTrashed()->count(),
            'added_today' => Tenant::query()->whereDate('created_at', today())->count(),
            'last_7_days' => Tenant::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'newest' => $newest === null ? null : [
                'name' => $newest->name,
                'slug' => $newest->getKey(),
                'created_at' => $newest->created_at->toIso8601String(),
            ],
        ];
    }

    /**
     * New workspaces per day for the trailing 30 days, zero-filled and oldest first.
     * Bucketed in PHP rather than SQL so the result does not depend on the database's
     * date functions.
     *
     * @return list<array{date: string, label: string, count: int}>
     */
    private function signups(): array
    {
        $byDay = Tenant::query()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->pluck('created_at')
            ->countBy(fn ($createdAt): string => $createdAt->format('Y-m-d'));

        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $key = $day->format('Y-m-d');

            $days[] = [
                'date' => $key,
                'label' => $day->format('M j'),
                'count' => (int) $byDay->get($key, 0),
            ];
        }

        return $days;
    }
}

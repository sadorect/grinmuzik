<?php

namespace App\Jobs;

use App\Models\PageView;
use App\Support\PageViewLocationResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResolvePageViewLocation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $pageViewId) {}

    public function handle(PageViewLocationResolver $resolver): void
    {
        $pageView = PageView::find($this->pageViewId);

        if (! $pageView) {
            return;
        }

        $resolver->enrich($pageView);
    }
}

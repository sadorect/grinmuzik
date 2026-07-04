<?php

namespace App\Filament\Pages;

use App\Jobs\SendNewsletterJob;
use App\Models\ArtistProfile;
use App\Models\NewsletterDraft;
use App\Models\Track;
use App\Models\User;
use App\Services\NewsletterAiService;
use App\Support\AdminPanelAccess;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Throwable;

class NewsletterGenerator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-envelope';

    protected static string | \UnitEnum | null $navigationGroup = 'Site Content';

    protected static ?string $navigationLabel = 'Newsletter';

    protected string $view = 'filament.pages.newsletter-generator';

    public ?array $data = [];

    public ?int $currentDraftId = null;

    public string $draftSubject = '';

    public string $draftPeriod = '';

    public string $draftBody = '';

    public string $draftStatus = '';

    public static function canAccess(): bool
    {
        return AdminPanelAccess::allows('manage settings');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'period' => 'Week of ' . now()->startOfWeek()->format('M j, Y'),
            'subject' => '',
            'tone' => 'Friendly',
            'include_charts' => true,
            'include_new_artists' => true,
            'include_platform_stats' => true,
            'custom_note' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Newsletter Variables')
                    ->description('Configure what this newsletter covers, then generate a draft with AI.')
                    ->schema([
                        Forms\Components\TextInput::make('period')
                            ->label('Period')
                            ->placeholder('Week of Jul 1, 2026')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('subject')
                            ->label('Email subject line')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('tone')
                            ->label('Tone')
                            ->options([
                                'Friendly' => 'Friendly',
                                'Professional' => 'Professional',
                                'Hype' => 'Hype',
                                'Inspirational' => 'Inspirational',
                            ])
                            ->default('Friendly')
                            ->required(),
                        Forms\Components\Textarea::make('custom_note')
                            ->label('Personal note (optional)')
                            ->helperText('A personal message from the team to weave into the newsletter.')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('include_charts')
                            ->label('Include top charts')
                            ->default(true),
                        Forms\Components\Toggle::make('include_new_artists')
                            ->label('Include new artists')
                            ->default(true),
                        Forms\Components\Toggle::make('include_platform_stats')
                            ->label('Include platform stats')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public function generate(NewsletterAiService $aiService): void
    {
        $state = $this->form->getState();

        $data = [];

        if (! empty($state['include_charts'])) {
            $data['charts'] = Track::query()
                ->published()
                ->orderByDesc('play_count')
                ->limit(10)
                ->with('artistProfile')
                ->get()
                ->map(fn (Track $track): array => [
                    'title' => $track->title,
                    'artist' => $track->artistProfile?->display_name ?? 'Unknown artist',
                    'plays' => (int) $track->play_count,
                ])
                ->all();
        }

        if (! empty($state['include_new_artists'])) {
            $data['new_artists'] = ArtistProfile::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->limit(20)
                ->get()
                ->map(fn (ArtistProfile $artist): array => ['name' => $artist->display_name])
                ->all();
        }

        if (! empty($state['include_platform_stats'])) {
            $data['stats'] = [
                'New listeners this period' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'Active artists' => ArtistProfile::where('is_active', true)->count(),
                'Total plays' => (int) Track::sum('play_count'),
            ];
        }

        $variables = [
            'period' => $state['period'],
            'subject' => $state['subject'],
            'tone' => $state['tone'],
            'custom_note' => $state['custom_note'] ?? '',
        ];

        try {
            $body = $aiService->generate($variables, $data);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Newsletter generation failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $draft = NewsletterDraft::create([
            'subject' => $variables['subject'],
            'period' => $variables['period'],
            'variables' => $variables,
            'body' => $body,
            'status' => 'draft',
            'generated_at' => now(),
        ]);

        $this->loadDraft($draft);
        unset($this->recentDrafts);

        Notification::make()
            ->title('Draft generated')
            ->success()
            ->send();
    }

    public function approve(): void
    {
        $draft = $this->requireCurrentDraft();

        if (! $draft) {
            return;
        }

        $draft->update([
            'body' => $this->draftBody,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->loadDraft($draft);
        unset($this->recentDrafts);

        Notification::make()
            ->title('Draft approved')
            ->success()
            ->send();
    }

    public function sendNewsletter(): void
    {
        $draft = $this->requireCurrentDraft();

        if (! $draft || ! $draft->isApproved()) {
            Notification::make()
                ->title('The draft must be approved before it can be sent')
                ->danger()
                ->send();

            return;
        }

        SendNewsletterJob::dispatch($draft);

        Notification::make()
            ->title('Newsletter queued for sending')
            ->success()
            ->send();
    }

    public function viewDraft(int $draftId): void
    {
        $draft = NewsletterDraft::find($draftId);

        if ($draft) {
            $this->loadDraft($draft);
        }
    }

    public function approveDraft(int $draftId): void
    {
        $draft = NewsletterDraft::find($draftId);

        if ($draft && $draft->isDraft()) {
            $draft->update(['status' => 'approved', 'approved_at' => now()]);

            if ($this->currentDraftId === $draft->id) {
                $this->loadDraft($draft);
            }

            unset($this->recentDrafts);

            Notification::make()
                ->title('Draft approved')
                ->success()
                ->send();
        }
    }

    public function resendDraft(int $draftId): void
    {
        $draft = NewsletterDraft::find($draftId);

        if ($draft && $draft->isSent()) {
            SendNewsletterJob::dispatch($draft);

            Notification::make()
                ->title('Newsletter re-queued for sending')
                ->success()
                ->send();
        }
    }

    #[Computed]
    public function recentDrafts(): Collection
    {
        return NewsletterDraft::latest()->take(10)->get();
    }

    protected function loadDraft(NewsletterDraft $draft): void
    {
        $this->currentDraftId = $draft->id;
        $this->draftSubject = $draft->subject;
        $this->draftPeriod = $draft->period;
        $this->draftBody = $draft->body;
        $this->draftStatus = $draft->status;
    }

    protected function requireCurrentDraft(): ?NewsletterDraft
    {
        if (! $this->currentDraftId) {
            Notification::make()
                ->title('No draft selected')
                ->danger()
                ->send();

            return null;
        }

        return NewsletterDraft::find($this->currentDraftId);
    }
}

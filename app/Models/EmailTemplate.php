<?php

namespace App\Models;

use App\Support\Mail\EmailCopy;
use App\Support\Mail\EmailTemplateCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Admin-edited wording for one email. Defaults live in EmailTemplateCatalog;
 * a row holds the current (possibly edited) copy of every field.
 */
class EmailTemplate extends Model
{
    protected $fillable = ['key', ...EmailTemplateCatalog::FIELDS];

    /**
     * The wording for $key with placeholders filled from $vars.
     *
     * A var set to null marks "not applicable": body paragraphs that use it
     * are dropped, and elsewhere it renders as nothing.
     */
    public static function copy(string $key, array $vars = []): EmailCopy
    {
        $fields = static::current($key);
        $vars = array_merge(static::globalVars($vars['recipient_name'] ?? null), $vars);

        $fill = fn (?string $text): ?string => filled($text) ? trim(static::fillPlaceholders($text, $vars)) : null;

        $lines = collect(preg_split('/\R\s*\R/', (string) $fields['body']))
            ->map(fn (string $paragraph) => trim($paragraph))
            ->filter(fn (string $paragraph) => $paragraph !== '' && ! static::usesMissingVar($paragraph, $vars))
            ->map(fn (string $paragraph) => static::fillPlaceholders($paragraph, $vars))
            ->values()
            ->all();

        return new EmailCopy(
            subject: $fill($fields['subject']) ?? '',
            preheader: $fill($fields['preheader']),
            eyebrow: $fill($fields['eyebrow']),
            title: $fill($fields['title']) ?? '',
            intro: $fill($fields['intro']),
            lines: $lines,
            ctaLabel: $fill($fields['cta_label']),
            footerNote: $fill($fields['footer_note']),
        );
    }

    /** Current field values: the edited row when there is one, else the defaults. */
    public static function current(string $key): array
    {
        $defaults = EmailTemplateCatalog::defaults($key);
        $row = static::row($key);

        if (! $row) {
            return $defaults;
        }

        // Fields this template doesn't expose keep their defaults.
        $editable = EmailTemplateCatalog::fields($key);

        return collect($defaults)
            ->map(fn (?string $default, string $field) => in_array($field, $editable, true) ? $row->{$field} : $default)
            ->all();
    }

    /** Insert a row for any catalog email that doesn't have one yet. Never touches edited rows. */
    public static function syncCatalog(): int
    {
        $existing = static::query()->pluck('key')->all();
        $added = 0;

        foreach (array_keys(EmailTemplateCatalog::all()) as $key) {
            if (! in_array($key, $existing, true)) {
                static::query()->create(['key' => $key, ...EmailTemplateCatalog::defaults($key)]);
                $added++;
            }
        }

        return $added;
    }

    public function definition(): array
    {
        return EmailTemplateCatalog::get($this->key);
    }

    public function isCustomised(): bool
    {
        $defaults = EmailTemplateCatalog::defaults($this->key);

        foreach (EmailTemplateCatalog::fields($this->key) as $field) {
            if (static::normalise($this->{$field}) !== static::normalise($defaults[$field])) {
                return true;
            }
        }

        return false;
    }

    public function resetToDefault(): void
    {
        $this->update(EmailTemplateCatalog::defaults($this->key));
    }

    /**
     * Read fresh every time: the queue worker is long-lived, so any cache here
     * would keep sending old wording after an admin edit.
     */
    protected static function row(string $key): ?self
    {
        try {
            return Schema::hasTable('email_templates')
                ? static::query()->where('key', $key)->first()
                : null;
        } catch (Throwable $exception) {
            // Mail must still go out if the table is unavailable.
            report($exception);

            return null;
        }
    }

    protected static function globalVars(?string $recipientName): array
    {
        try {
            $siteName = Schema::hasTable('site_settings') ? SiteSetting::current()?->effective_site_name : null;
        } catch (Throwable) {
            $siteName = null;
        }

        return [
            'site_name' => $siteName ?: config('app.name', 'GrinMuzik'),
            'recipient_name' => $recipientName ?? '',
        ];
    }

    protected static function fillPlaceholders(string $text, array $vars): string
    {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/', function (array $match) use ($vars) {
            // Unknown placeholders are left as typed so a typo is visible in a test send.
            return array_key_exists($match[1], $vars) ? (string) $vars[$match[1]] : $match[0];
        }, $text);
    }

    protected static function usesMissingVar(string $text, array $vars): bool
    {
        preg_match_all('/\{([a-z0-9_]+)\}/', $text, $matches);

        foreach ($matches[1] as $name) {
            if (array_key_exists($name, $vars) && ($vars[$name] === null || $vars[$name] === '')) {
                return true;
            }
        }

        return false;
    }

    protected static function normalise(?string $value): string
    {
        return trim(str_replace("\r\n", "\n", (string) $value));
    }
}

{{ $subject }}

{{ str_repeat('=', strlen($subject)) }}

@if($recipientName)
Hi {{ $recipientName }},

@endif
{{ $title }}

@if($intro)
{{ $intro }}

@endif
@foreach($messageLines as $line)
{{ $line }}

@endforeach
@if(!empty($highlights))
Highlights:
@foreach($highlights as $highlight)
- {{ $highlight['label'] }}: {{ $highlight['value'] }}
@endforeach

@endif
@if($ctaLabel && $ctaUrl)
{{ $ctaLabel }}: {{ $ctaUrl }}

@endif
@if($secondaryCtaLabel && $secondaryCtaUrl)
{{ $secondaryCtaLabel }}: {{ $secondaryCtaUrl }}

@endif
@if($footerNote)
{{ $footerNote }}

@endif
{{ $siteName }}: {{ $siteUrl }}
Support: {{ $contactUrl }}

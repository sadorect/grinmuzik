{{--
    Shown on every public-site page while the super admin is signed in as
    someone. Inline styles on purpose: it must look the same on every layout
    and not depend on which Tailwind utilities made it into the build.
--}}
@php($gmImpersonation = app(\App\Support\Impersonation::class))
@if (auth()->check() && $gmImpersonation->isActive())
    <div id="gm-impersonation-banner" role="status"
         style="position:fixed;left:50%;top:74px;transform:translateX(-50%);z-index:2147483600;display:flex;align-items:center;gap:10px;max-width:calc(100vw - 24px);padding:7px 8px 7px 14px;border-radius:9999px;background:#b45309;color:#fff;font:600 13px/1.3 system-ui,-apple-system,'Segoe UI',sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.35);">
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">
            Signed in as {{ auth()->user()->name }}
        </span>
        <form method="POST" action="{{ route('impersonation.stop') }}" style="margin:0;flex-shrink:0;">
            @csrf
            <button type="submit"
                    style="cursor:pointer;border:0;border-radius:9999px;padding:6px 12px;background:#fff;color:#92400e;font:700 12px/1 system-ui,-apple-system,'Segoe UI',sans-serif;white-space:nowrap;">
                Return to my account
            </button>
        </form>
    </div>
@endif

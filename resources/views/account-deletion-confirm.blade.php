@extends('layouts.glass-app')

@section('content')
    <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-xl">
            <div class="rounded-lg border border-red-200 bg-white p-6 dark:border-red-800 dark:bg-gray-800">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Permanently delete this account?
                </h1>

                <p class="mt-2 text-gray-600 dark:text-gray-300">
                    You are about to delete the GrinMuzik account for
                    <strong>{{ $email }}</strong>. This cannot be undone.
                </p>

                <h2 class="mt-6 font-semibold text-gray-900 dark:text-white">What gets deleted</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ($service->deletedItems() as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <h2 class="mt-5 font-semibold text-gray-900 dark:text-white">What we keep</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ($service->retainedItems() as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                {{-- A POST, not the emailed GET: mail clients and security
                     scanners prefetch links, and a GET that deletes would let a
                     scanner destroy an account nobody clicked. --}}
                <form method="POST" action="{{ route('account.deletion.destroy', ['token' => $token]) }}" class="mt-8 flex flex-col gap-3 sm:flex-row">
                    @csrf

                    <button
                        type="submit"
                        class="inline-flex justify-center rounded-lg bg-red-600 px-5 py-3 font-semibold text-white transition hover:bg-red-700"
                    >
                        Yes, permanently delete my account
                    </button>

                    <a
                        href="{{ route('home') }}"
                        class="inline-flex justify-center rounded-lg border border-gray-300 px-5 py-3 font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Cancel, keep my account
                    </a>
                </form>
            </div>
        </div>
    </div>
@endsection

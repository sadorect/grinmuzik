@extends('layouts.glass-app')

@section('content')
    <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-xl">
            <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    This deletion link is no longer valid
                </h1>

                <p class="mt-2 text-gray-600 dark:text-gray-300">
                    It may have expired, already been used, or been replaced by a
                    newer request. Nothing has been deleted.
                </p>

                <a
                    href="{{ route('account.deletion') }}"
                    class="mt-6 inline-flex rounded-lg bg-red-600 px-5 py-3 font-semibold text-white transition hover:bg-red-700"
                >
                    Request a new deletion link
                </a>
            </div>
        </div>
    </div>
@endsection

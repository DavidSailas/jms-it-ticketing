@php($ticketLink = request()->is('tickets/*'))
@auth
    <x-app-layout>
        <x-slot name="header">Not available</x-slot>

        <div class="mx-auto max-w-md py-12 text-center sm:py-20">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Zm-3-7.5h6"/></svg>
            </span>
            <h1 class="mt-5 text-xl font-bold text-slate-900">
                {{ $ticketLink ? 'This ticket is not available to you' : 'We could not find that page' }}
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                @if ($ticketLink)
                    It may have been reassigned, closed or removed, or the link may be incorrect.
                @else
                    The link may be old or incorrect, or you may not have access to it.
                @endif
            </p>
            <div class="mt-6 flex flex-col justify-center gap-2 sm:flex-row">
                <a href="{{ route('tickets.index') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Go to tickets</a>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Dashboard</a>
            </div>
        </div>
    </x-app-layout>
@else
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Page not found</title>
        <style>
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #0f172a; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; text-align: center; padding: 1.5rem; }
            h1 { margin: 0 0 .5rem; font-size: 1.25rem; }
            p { margin: 0 0 1.25rem; color: #64748b; font-size: .9rem; }
            a { display: inline-block; padding: .65rem 1.1rem; border-radius: .6rem; background: #12284a; color: #fff; font-weight: 600; font-size: .9rem; text-decoration: none; }
        </style>
    </head>
    <body>
        <main>
            <h1>We could not find that page</h1>
            <p>The link may be old or incorrect.</p>
            <a href="{{ url('/') }}">Go to the home page</a>
        </main>
    </body>
    </html>
@endauth

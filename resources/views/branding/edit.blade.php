<x-app-layout>
    <x-slot name="header">Branding</x-slot>

    <div class="mb-5">
        <h2 class="text-xl font-extrabold tracking-tight text-brand-800">Make it yours</h2>
        <p class="mt-0.5 text-sm text-slate-500">Set {{ $company->name }}'s name, logo and colour. Everyone in your company sees the change straight away.</p>
    </div>

    <div class="max-w-3xl">
        <x-companies.branding-form :company="$company" :action="route('branding.update')" :show-name="true" />
        <p class="mt-3 text-xs text-slate-500">The sign-in page always shows the JMS One IT logo, because the system doesn't know your company until you sign in.</p>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">New Ticket</x-slot>

    <div class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
        @include('tickets._form')
    </div>
</x-app-layout>

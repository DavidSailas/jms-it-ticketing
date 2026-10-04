<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold tracking-tight text-brand-800">Forgot your password?</h1>
        <p class="mt-2 text-sm text-slate-500">Enter your email and we'll send you a link to choose a new one.</p>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus placeholder="you@company.com"
                   class="block w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 @error('email') border-red-400 @enderror">
            @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button class="w-full rounded-lg bg-brand-800 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">Email reset link</button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-500">
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-800 hover:underline">&larr; Back to sign in</a>
    </p>
</x-guest-layout>

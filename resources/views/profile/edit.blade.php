<x-app-layout title="Profile & password" :breadcrumbs="['Dashboard' => route('dashboard'), 'Profile' => null]">
    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('profile.update') }}" class="card" novalidate>
            @csrf @method('PATCH')
            <div class="card-header"><h2 class="card-title">Administrator profile</h2></div>
            <div class="card-body space-y-4">
                <div>
                    <label for="name" class="label">Name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" @class(['input', 'input-error' => $errors->profile->has('name')])>
                    @if ($errors->profile->has('name'))<p class="field-error">{{ $errors->profile->first('name') }}</p>@endif
                </div>
                <div>
                    <label for="email" class="label">Sign-in email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="username" @class(['input', 'input-error' => $errors->profile->has('email')])>
                    @if ($errors->profile->has('email'))<p class="field-error">{{ $errors->profile->first('email') }}</p>@endif
                </div>
                <p class="text-xs text-slate-500">Last sign-in: {{ $user->last_login_at?->format('d M Y, H:i') ?? '—' }}</p>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3">
                <button type="submit" class="btn btn-primary"><x-icon name="check" /> Save profile</button>
            </div>
        </form>

        <form method="POST" action="{{ route('profile.password') }}" class="card" novalidate x-data="{ show: false }">
            @csrf @method('PUT')
            <div class="card-header">
                <h2 class="card-title">Change password</h2>
                <button type="button" class="btn btn-sm btn-ghost" @click="show = !show" x-text="show ? 'Hide' : 'Show'">Show</button>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label for="current_password" class="label">Current password</label>
                    <input id="current_password" name="current_password" :type="show ? 'text' : 'password'" type="password" autocomplete="current-password" @class(['input', 'input-error' => $errors->password->has('current_password')])>
                    @if ($errors->password->has('current_password'))<p class="field-error">{{ $errors->password->first('current_password') }}</p>@endif
                </div>
                <div>
                    <label for="password" class="label">New password</label>
                    <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" @class(['input', 'input-error' => $errors->password->has('password')])>
                    @if ($errors->password->has('password'))<p class="field-error">{{ $errors->password->first('password') }}</p>@else<p class="help">At least 8 characters with upper- and lower-case letters and a number.</p>@endif
                </div>
                <div>
                    <label for="password_confirmation" class="label">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" class="input">
                </div>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3">
                <button type="submit" class="btn btn-primary"><x-icon name="lock" /> Update password</button>
            </div>
        </form>
    </div>
</x-app-layout>

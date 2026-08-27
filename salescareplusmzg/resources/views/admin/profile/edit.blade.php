<x-admin.layout title="My Profile">

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900">My Profile</h2>
        <p class="mt-1 text-sm text-slate-500">Update your admin login name, email and password.</p>
    </div>

    <form method="POST" action="{{ route('admin.profile.update') }}" class="max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @method('PUT')

        <x-admin.field label="Name" name="name" :value="$user->name" required />
        <x-admin.field label="Email Address" name="email" type="email" :value="$user->email" required />

        <div class="border-t border-slate-100 pt-5">
            <p class="mb-4 text-sm font-medium text-slate-700">Change Password <span class="font-normal text-slate-400">(leave blank to keep current password)</span></p>
            <div class="space-y-5">
                <x-admin.field label="Current Password" name="current_password" type="password" hint="Required only if setting a new password." />
                <x-admin.field label="New Password" name="password" type="password" />
                <x-admin.field label="Confirm New Password" name="password_confirmation" type="password" />
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            Save Changes
        </button>
    </form>

</x-admin.layout>

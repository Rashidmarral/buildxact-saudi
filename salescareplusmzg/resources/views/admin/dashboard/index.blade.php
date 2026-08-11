<x-admin.layout title="Dashboard">

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900">Welcome back, {{ auth()->user()->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">Here's what's happening with your website content.</p>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            ['label' => 'Products', 'value' => $stats['products'], 'icon' => 'pill', 'route' => 'admin.products.index'],
            ['label' => 'Categories', 'value' => $stats['categories'], 'icon' => 'pill', 'route' => 'admin.product-categories.index'],
            ['label' => 'Principals', 'value' => $stats['principals'], 'icon' => 'building', 'route' => 'admin.principals.index'],
            ['label' => 'Testimonials', 'value' => $stats['testimonials'], 'icon' => 'quote', 'route' => 'admin.testimonials.index'],
            ['label' => 'Team Members', 'value' => $stats['team_members'], 'icon' => 'users', 'route' => 'admin.team-members.index'],
            ['label' => 'FAQs', 'value' => $stats['faqs'], 'icon' => 'quote', 'route' => 'admin.faqs.index'],
            ['label' => 'Custom Pages', 'value' => $stats['pages'], 'icon' => 'file-check', 'route' => 'admin.pages.index'],
            ['label' => 'Newsletter Subscribers', 'value' => $stats['newsletter_subscribers'], 'icon' => 'send', 'route' => 'admin.newsletter-subscribers.index'],
            ['label' => 'Unread Messages', 'value' => $stats['unread_messages'], 'icon' => 'mail', 'route' => 'admin.contact-messages.index'],
        ] as $card)
            <a href="{{ route($card['route']) }}" class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-teal-300 hover:shadow-sm">
                <x-icon :name="$card['icon']" class="h-5 w-5 text-teal-700" />
                <p class="mt-3 text-2xl font-bold text-slate-900">{{ $card['value'] }}</p>
                <p class="text-xs text-slate-500">{{ $card['label'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-8 rounded-xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h3 class="font-semibold text-slate-900">Recent Contact Messages</h3>
            <a href="{{ route('admin.contact-messages.index') }}" class="text-sm font-medium text-teal-700 hover:underline">View all &rarr;</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($recentMessages as $message)
                <a href="{{ route('admin.contact-messages.show', $message) }}" class="flex items-center justify-between px-5 py-3 text-sm transition hover:bg-slate-50">
                    <span>
                        <span class="font-medium text-slate-800">{{ $message->name }}</span>
                        <span class="text-slate-400">— {{ $message->subject ?: 'No subject' }}</span>
                    </span>
                    <span class="flex items-center gap-2 text-xs text-slate-400">
                        @unless ($message->is_read)
                            <span class="rounded-full bg-coral-500 px-2 py-0.5 font-semibold text-white">New</span>
                        @endunless
                        {{ $message->created_at->diffForHumans() }}
                    </span>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">No messages yet.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-dashed border-teal-300 bg-teal-50 p-5 text-sm text-teal-800">
        <p class="font-semibold">Quick tips</p>
        <ul class="mt-2 list-inside list-disc space-y-1 text-teal-700">
            <li>Update contact details, social links and stats under <a href="{{ route('admin.settings.edit') }}" class="underline">Site Settings</a>.</li>
            <li>Build a brand-new page (with its own URL) under <a href="{{ route('admin.pages.index') }}" class="underline">Pages</a>, then add it to the menu under <a href="{{ route('admin.nav-items.index') }}" class="underline">Navigation</a>.</li>
            <li>Set a homepage video banner from <a href="{{ route('admin.settings.edit') }}" class="underline">Site Settings</a>.</li>
        </ul>
    </div>

</x-admin.layout>

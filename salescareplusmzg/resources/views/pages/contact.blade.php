<x-layout title="Contact Us" description="Get in touch with Sales Care Plus MZG for medicine orders, pharmacy account setup, or general enquiries. Muzaffargarh, Punjab, Pakistan.">

    <section class="bg-navy-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <h1 class="hero-fade-in text-4xl font-bold tracking-tight text-white sm:text-5xl">Contact Us</h1>
            <p class="hero-fade-in hero-fade-in-delay-1 mt-6 text-lg leading-relaxed text-navy-200">
                Get in touch with our team — we'll respond within 24 hours.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-5">

            <div class="reveal lg:col-span-3">
                <h2 class="text-2xl font-bold text-navy-900">Send Us a <span class="text-sky-500">Message</span></h2>

                <div class="mt-6 rounded-3xl border border-slate-100 p-8 shadow-sm">
                    @if (session('status'))
                        <div class="reveal-scale mb-6 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                            <x-icon name="check-circle" class="h-5 w-5 shrink-0" />
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.store') }}" class="space-y-5">
                        @csrf
                        <div>
                            <label for="name" class="flex items-center gap-1.5 text-sm font-medium text-navy-900">
                                <x-icon name="users" class="h-4 w-4 text-sky-500" /> Full Name
                            </label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="Enter your full name"
                                class="mt-1.5 w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm transition focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            @error('name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="email" class="flex items-center gap-1.5 text-sm font-medium text-navy-900">
                                    <x-icon name="mail" class="h-4 w-4 text-sky-500" /> Email Address
                                </label>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required placeholder="your@email.com"
                                    class="mt-1.5 w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm transition focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                                @error('email')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="phone" class="flex items-center gap-1.5 text-sm font-medium text-navy-900">
                                    <x-icon name="phone" class="h-4 w-4 text-sky-500" /> Phone Number
                                </label>
                                <input type="text" name="phone" id="phone" value="{{ old('phone') }}" placeholder="+92-XXX-XXXXXXX"
                                    class="mt-1.5 w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm transition focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                                @error('phone')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div>
                            <label for="subject" class="flex items-center gap-1.5 text-sm font-medium text-navy-900">
                                <x-icon name="badge-check" class="h-4 w-4 text-sky-500" /> Subject
                            </label>
                            <select name="subject" id="subject"
                                class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm transition focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                                @foreach ([
                                    'General Inquiry',
                                    'Product Inquiry',
                                    'New Pharmacy Account',
                                    'Partnership Opportunity',
                                    'Support Request',
                                    'Other',
                                ] as $option)
                                    <option value="{{ $option }}" @selected(old('subject') === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('subject')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="message" class="flex items-center gap-1.5 text-sm font-medium text-navy-900">
                                <x-icon name="quote" class="h-4 w-4 text-sky-500" /> Message
                            </label>
                            <textarea name="message" id="message" rows="5" required placeholder="Write your message here…"
                                class="mt-1.5 w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm transition focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">{{ old('message') }}</textarea>
                            @error('message')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="group inline-flex items-center gap-2 rounded-full bg-navy-900 px-6 py-3.5 font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-navy-800 hover:shadow-lg">
                            <x-icon name="send" class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" /> Send Message
                        </button>
                    </form>
                </div>
            </div>

            <div class="reveal lg:col-span-2">
                <h2 class="text-2xl font-bold text-navy-900">Get in <span class="text-sky-500">Touch</span></h2>
                <p class="mt-3 text-sm leading-relaxed text-slate-500">
                    We'd love to hear from you. Reach out to us through any of these channels.
                </p>

                <div class="reveal-stagger mt-6 space-y-5">
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-600">
                            <x-icon name="map-pin" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold text-navy-900">Address</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ config('company.address') }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-600">
                            <x-icon name="phone" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold text-navy-900">Phone</h3>
                            <a href="tel:{{ config('company.phone') }}" class="mt-1 block text-sm text-slate-500 hover:text-sky-600">{{ config('company.phone') }}</a>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-600">
                            <x-icon name="mail" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold text-navy-900">Email</h3>
                            <a href="mailto:{{ config('company.email') }}" class="mt-1 block text-sm text-slate-500 hover:text-sky-600">{{ config('company.email') }}</a>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-600">
                            <x-icon name="clock" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold text-navy-900">Working Hours</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ config('company.hours') }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <a href="{{ config('company.social.facebook') }}" class="rounded-full border border-slate-200 p-2.5 text-slate-500 transition hover:border-sky-400 hover:text-sky-600" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M13 22v-8h2.7l.4-3.3H13V8.6c0-.9.3-1.6 1.7-1.6H16V4.1C15.6 4 14.6 4 13.5 4 11 4 9.3 5.5 9.3 8.3v2.4H6.6V14h2.7v8h3.7Z"/></svg>
                    </a>
                    <a href="{{ config('company.social.linkedin') }}" class="rounded-full border border-slate-200 p-2.5 text-slate-500 transition hover:border-sky-400 hover:text-sky-600" aria-label="LinkedIn">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M4.98 3.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM3 9h4v12H3V9Zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.3c0-1.27-.02-2.9-1.77-2.9-1.77 0-2.04 1.38-2.04 2.8V21h-4V9Z"/></svg>
                    </a>
                    <a href="{{ config('company.social.twitter') }}" class="rounded-full border border-slate-200 p-2.5 text-slate-500 transition hover:border-sky-400 hover:text-sky-600" aria-label="Twitter">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M22 5.9c-.7.3-1.5.6-2.3.7.8-.5 1.5-1.3 1.8-2.3-.8.5-1.7.8-2.6 1a4.1 4.1 0 0 0-7 3.7A11.6 11.6 0 0 1 3.4 4.6a4.1 4.1 0 0 0 1.3 5.5c-.7 0-1.3-.2-1.9-.5v.1c0 2 1.4 3.6 3.3 4a4.2 4.2 0 0 1-1.9.1 4.1 4.1 0 0 0 3.8 2.9A8.3 8.3 0 0 1 2 18.4a11.6 11.6 0 0 0 6.3 1.9c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2.2Z"/></svg>
                    </a>
                    <a href="{{ config('company.social.instagram') }}" class="rounded-full border border-slate-200 p-2.5 text-slate-500 transition hover:border-sky-400 hover:text-sky-600" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Map placeholder --}}
        <div class="reveal-scale mt-16 flex aspect-[21/8] items-center justify-center overflow-hidden rounded-3xl border border-slate-100 bg-slate-100">
            <div class="flex flex-col items-center gap-3 text-center text-slate-400">
                <x-icon name="map-pin" class="h-8 w-8" />
                <p class="text-sm font-medium">Map view coming soon</p>
                <p class="max-w-xs text-xs">{{ config('company.address') }}</p>
            </div>
        </div>
    </section>

</x-layout>

<x-layout title="Contact Us" description="Get in touch with Sales Care Plus MZG for medicine orders, pharmacy account setup, or general enquiries. Muzaffargarh, Punjab, Pakistan.">

    <section class="bg-leaf-pattern bg-leaf-50 py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-leaf-100 px-4 py-1.5 text-sm font-medium text-leaf-700">
                <x-icon name="mail" class="h-4 w-4" /> Get in Touch
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-leaf-950 sm:text-5xl">We'd Love to Hear From You</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-stone-600">
                Whether you're a pharmacy looking to set up a supply account or just have a question —
                our team in Muzaffargarh is ready to help.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-5">

            <div class="lg:col-span-2">
                <div class="reveal-stagger space-y-6">
                    <div class="flex items-start gap-4 rounded-2xl border border-stone-100 p-6 transition duration-300 hover:-translate-y-1 hover:shadow-md">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-leaf-100 text-leaf-700">
                            <x-icon name="map-pin" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold text-stone-800">Our Address</h3>
                            <p class="mt-1 text-sm text-stone-500">{{ config('company.address') }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 rounded-2xl border border-stone-100 p-6 transition duration-300 hover:-translate-y-1 hover:shadow-md">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-leaf-100 text-leaf-700">
                            <x-icon name="phone" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold text-stone-800">Phone &amp; WhatsApp</h3>
                            <a href="tel:{{ config('company.phone') }}" class="mt-1 block text-sm text-stone-500 hover:text-leaf-700">{{ config('company.phone') }}</a>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 rounded-2xl border border-stone-100 p-6 transition duration-300 hover:-translate-y-1 hover:shadow-md">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-leaf-100 text-leaf-700">
                            <x-icon name="mail" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold text-stone-800">Email</h3>
                            <a href="mailto:{{ config('company.email') }}" class="mt-1 block text-sm text-stone-500 hover:text-leaf-700">{{ config('company.email') }}</a>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 rounded-2xl border border-stone-100 p-6 transition duration-300 hover:-translate-y-1 hover:shadow-md">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-leaf-100 text-leaf-700">
                            <x-icon name="clock" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold text-stone-800">Business Hours</h3>
                            <p class="mt-1 text-sm text-stone-500">{{ config('company.hours') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reveal lg:col-span-3">
                <div class="rounded-3xl border border-stone-100 p-8 shadow-sm">
                    @if (session('status'))
                        <div class="reveal-scale mb-6 flex items-center gap-2 rounded-xl bg-leaf-50 px-4 py-3 text-sm font-medium text-leaf-700">
                            <x-icon name="check-circle" class="h-5 w-5 shrink-0" />
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.store') }}" class="space-y-5">
                        @csrf
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-medium text-stone-700">Full Name</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    class="mt-1.5 w-full rounded-lg border border-stone-200 px-4 py-2.5 text-sm transition focus:border-leaf-500 focus:outline-none focus:ring-1 focus:ring-leaf-500">
                                @error('name')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-stone-700">Phone Number</label>
                                <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                                    class="mt-1.5 w-full rounded-lg border border-stone-200 px-4 py-2.5 text-sm transition focus:border-leaf-500 focus:outline-none focus:ring-1 focus:ring-leaf-500">
                                @error('phone')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-stone-700">Email Address</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                class="mt-1.5 w-full rounded-lg border border-stone-200 px-4 py-2.5 text-sm transition focus:border-leaf-500 focus:outline-none focus:ring-1 focus:ring-leaf-500">
                            @error('email')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="subject" class="block text-sm font-medium text-stone-700">Subject</label>
                            <input type="text" name="subject" id="subject" value="{{ old('subject') }}" placeholder="e.g. New pharmacy account, product enquiry…"
                                class="mt-1.5 w-full rounded-lg border border-stone-200 px-4 py-2.5 text-sm transition focus:border-leaf-500 focus:outline-none focus:ring-1 focus:ring-leaf-500">
                            @error('subject')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-stone-700">Message</label>
                            <textarea name="message" id="message" rows="5" required
                                class="mt-1.5 w-full rounded-lg border border-stone-200 px-4 py-2.5 text-sm transition focus:border-leaf-500 focus:outline-none focus:ring-1 focus:ring-leaf-500">{{ old('message') }}</textarea>
                            @error('message')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="group inline-flex items-center gap-2 rounded-full bg-leaf-600 px-6 py-3.5 font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-leaf-700 hover:shadow-lg">
                            Send Message <x-icon name="arrow-right" class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" />
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

</x-layout>

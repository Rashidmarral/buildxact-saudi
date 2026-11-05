@php($active = request()->routeIs($route.'*'))
<a href="{{ route($route) }}" class="block rounded-lg px-3 py-1.5 text-sm transition-colors {{ $active ? 'font-medium text-white bg-white/[0.06]' : 'text-slate-400 hover:text-white hover:bg-white/5' }}">{{ $label }}</a>

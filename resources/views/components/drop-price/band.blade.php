{{-- The Drop Price section shell: the #030309 void, the heat-spectrum hairline
     seam, and the two ambient glows. Shared by the homepage game band and the
     archive index/show pages so the game reads identically everywhere. Slot
     content supplies its own max-w/padding wrapper. --}}
<section {{ $attributes->merge(['class' => 'relative overflow-hidden text-slate-100']) }} style="background: #030309">
    <div class="absolute inset-x-0 top-0 h-px" style="background:linear-gradient(90deg,transparent,#38BDF8 18%,#FB923C 45%,#FF4D4D 72%,#FACC15 88%,transparent)"></div>
    <div class="pointer-events-none absolute -top-24 left-1/4 w-96 h-96 rounded-full bg-sky-500/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 right-10 w-80 h-80 rounded-full bg-rose-500/10 blur-3xl"></div>
    {{ $slot }}
</section>

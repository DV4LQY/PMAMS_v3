@props(['paginator'])

@if ($paginator->hasPages())

    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();

        $start = $paginator->firstItem();
        $end = $paginator->lastItem();
        $total = $paginator->total();
    @endphp

    <div x-data="{
                        jumpOpen:false,
                        page:'',

                        openJump(){

                            this.jumpOpen=true;

                            this.$nextTick(()=>{
                                this.$refs.pageInput.focus();
                                this.$refs.pageInput.select();
                            });

                        },

                            go(){

                        let p=parseInt(this.page);

                        if(isNaN(p)){
                            return;
                        }

                        if(p<1)p=1;

                        if(p>{{ $last }})p={{ $last }};

                        this.jumpOpen=false;

                        const url=new URL(window.location.href);

                        url.searchParams.set('page',p);

                        window.location=url.toString();

                    }
                        }" class="space-y-4">

        <div class="text-center text-sm text-gray-500">

            Showing

            <span class="font-medium">{{ $start }}</span>

            –

            <span class="font-medium">{{ $end }}</span>

            of

            <span class="font-medium">{{ $total }}</span>

            logs

        </div>

        <div class="flex justify-center items-center gap-2 flex-wrap">

            {{-- Previous --}}

            @if($paginator->onFirstPage())

                <span
                    class="flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-lg border border-gray-200 bg-gray-100 text-gray-400 opacity-40">

                    &lt;

                </span>

            @else

                <a href="{{ $paginator->previousPageUrl() }}"
                    class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 bg-white transition-all duration-150 hover:bg-gray-50">

                    &lt;

                </a>

            @endif

            @php

                $windowStart = max(2, $current - 1);
                $windowEnd = min($last - 1, $current + 1);

                if ($current <= 2) {
                    $windowStart = 2;
                    $windowEnd = min(4, $last - 1);
                }

                if ($current >= $last - 1) {
                    $windowStart = max(2, $last - 3);
                    $windowEnd = $last - 1;
                }

            @endphp

            {{-- First page --}}
            <a href="{{ $paginator->url(1) }}" class="flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 transition-all duration-150
                        {{ $current == 1
            ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
            : 'border-gray-300 bg-white hover:bg-gray-50' }}">

                1

            </a>

            @if($windowStart > 2)

                <button @click="openJump()"
                    class="flex h-10 min-w-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 hover:bg-gray-50 cursor-pointer">

                    ...

                </button>

            @endif

            @if($windowEnd >= $windowStart)
                @foreach(range($windowStart, $windowEnd) as $page)

                    <a href="{{ $paginator->url($page) }}" class="flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 transition-all duration-150
                                                    {{ $page == $current
                        ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                        : 'border-gray-300 bg-white hover:bg-gray-50' }}">

                        {{ $page }}

                    </a>

                @endforeach
            @endif

            @if($windowEnd < $last - 1)

                <button @click="openJump()"
                    class="flex h-10 min-w-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 hover:bg-gray-50 cursor-pointer">

                    ...

                </button>

            @endif

            @if($last > 1)

                <a href="{{ $paginator->url($last) }}" class="flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 transition-all duration-150
                                                {{ $current == $last
                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                    : 'border-gray-300 bg-white hover:bg-gray-50' }}">

                    {{ $last }}

                </a>

            @endif

            {{-- Next --}}

            @if($paginator->hasMorePages())

                <a href="{{ $paginator->nextPageUrl() }}"
                    class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 bg-white transition-all duration-150 hover:bg-gray-50">

                    &gt;

                </a>

            @else

                <span
                    class="flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-lg border border-gray-200 bg-gray-100 text-gray-400 opacity-40">

                    &gt;

                </span>

            @endif

        </div>



        <!-- Jump to page -->
        <div x-show="jumpOpen" x-transition.opacity @keydown.escape.window="jumpOpen=false" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="jumpOpen=false">

            <div class="w-80 rounded-xl bg-white shadow-xl ring-1 ring-gray-200 p-5 space-y-4">

                <div>

                    <h3 class="text-base font-semibold text-gray-800">
                        Go to page
                    </h3>

                    <p class="mt-1 text-sm text-gray-500">
                        Enter a page number between
                        <span class="font-medium">1</span>
                        and
                        <span class="font-medium">{{ $last }}</span>.
                    </p>

                </div>

                <input x-ref="pageInput" x-model="page" @keydown.enter.prevent="go()" type="number" min="1"
                    max="{{ $last }}"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    placeholder="Page number">

                <div class="flex justify-end gap-2">

                    <button @click="jumpOpen=false"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">

                        Cancel

                    </button>

                    <button @click="go()"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">

                        Go

                    </button>

                </div>

            </div>

        </div> {{-- popup ends --}}
    </div> {{-- x-data ends --}}
@endif
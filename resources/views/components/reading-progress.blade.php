<div x-data="readingProgress" class="fixed top-0 left-0 right-0 h-1 z-[60] bg-transparent pointer-events-none">
    <div class="h-full bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-400"
        style="width: 0"
        :style="`width: ${progress}%`"></div>
</div>

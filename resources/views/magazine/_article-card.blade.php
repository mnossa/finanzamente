<article class="group flex flex-col rounded-2xl overflow-hidden border border-surface-200 hover:shadow-md hover:border-surface-300 transition-all bg-white">
    <a href="{{ route('magazine.show', $article->slug) }}" class="block" tabindex="-1" aria-hidden="true">
        @if($article->cover_image_url)
            <div class="aspect-video overflow-hidden">
                <img src="{{ $article->cover_image_url }}"
                     alt="{{ $article->title }}"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                     loading="lazy">
            </div>
        @else
            <div class="aspect-video bg-gradient-to-br from-surface-100 to-surface-200 flex items-center justify-center">
                <span class="w-3 h-3 rounded-full" style="background-color: {{ $article->category->color }}"></span>
            </div>
        @endif
    </a>
    <div class="flex flex-col flex-1 p-5">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $article->category->color }}"></span>
            <a href="{{ route('magazine.category', $article->category->slug) }}"
               class="text-xs font-semibold uppercase tracking-wider text-surface-500 hover:text-primary-600 transition-colors">
                {{ $article->category->name }}
            </a>
            <span class="text-xs text-surface-400">· {{ $article->reading_time_minutes }} min</span>
        </div>
        <a href="{{ route('magazine.show', $article->slug) }}" class="block flex-1">
            <h2 class="text-base font-bold text-surface-900 mb-2 group-hover:text-primary-700 transition-colors leading-snug line-clamp-2">
                {{ $article->title }}
            </h2>
            <p class="text-sm text-surface-500 line-clamp-3 leading-relaxed">{{ $article->excerpt }}</p>
        </a>
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-surface-100 text-xs text-surface-400">
            <span>{{ $article->author_name }}</span>
            <time datetime="{{ $article->published_at->toDateString() }}">
                {{ $article->published_at->locale('it')->isoFormat('D MMM YYYY') }}
            </time>
        </div>
    </div>
</article>

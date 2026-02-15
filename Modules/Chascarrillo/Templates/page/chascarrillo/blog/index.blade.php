@extends('partial.layout.main')

@section('content')
<div class="alert alert-info">
    DEBUG: BLOG INDEX LOADED.<br>
    Logged: {{ \Alxarafe\Lib\Auth::isLogged() ? 'YES' : 'NO' }}<br>
    Main Menu Count: {{ isset($main_menu) ? count($main_menu) : 'NULL' }}
</div>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-4xl font-bold mb-8 text-center">{{ $title }}</h1>

    @if($posts->isEmpty())
        <p class="text-center text-gray-500">No hay entradas publicadas todavía.</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($posts as $post)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300">
                    <div class="p-6">
                        <div class="text-gray-500 text-sm mb-2">
                            {{ $post->published_at ? $post->published_at->format('d M, Y') : 'Borrador' }}
                        </div>
                        <h2 class="text-xl font-bold mb-3">
                            <a href="?controller=Blog&action=show&slug={{ $post->slug }}" class="hover:text-blue-600 transition-colors">
                                {{ $post->title }}
                            </a>
                        </h2>
                        <div class="text-gray-700 mb-4 line-clamp-3">
                            {{ Str::limit(strip_tags($post->content), 150) }}
                        </div>
                        <a href="?controller=Blog&action=show&slug={{ $post->slug }}" 
                           class="inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors">
                            Leer más
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

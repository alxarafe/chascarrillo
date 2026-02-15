@extends('partial.layout.main')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-4">
        <a href="?controller=Blog" class="text-blue-500 hover:underline">← Volver al blog</a>
    </div>

    <article class="bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-4xl font-bold mb-4">{{ $post->title }}</h1>
        <div class="text-gray-500 text-sm mb-6 border-b pb-4">
             Publicado el {{ $post->published_at ? $post->published_at->format('d M, Y \a \l\a\s H:i') : '' }}
        </div>

        <div class="prose max-w-none">
            {!! nl2br(e($post->content)) !!}
        </div>
    </article>
</div>
@endsection

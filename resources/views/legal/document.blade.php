{{--
    One legal document, drawn from the structure in config/legal-documents.php.

    The mobile app renders the same structure natively from `/api/v1/legal`, so
    anything added here as a new block type has to be handled there too, or the
    app will silently drop it.
--}}
@extends('legal.layout')

@section('title', $document['title'])
@section('description', $document['title'] . ' for ' . config('app.name') . '.')

@section('body')
    @foreach ($document['sections'] as $section)
        <h2>{{ $section['heading'] }}</h2>

        @foreach ($section['blocks'] as $block)
            @if ($block['type'] === 'p')
                <p>{{ $block['text'] }}</p>
            @elseif ($block['type'] === 'ul')
                <ul>
                    @foreach ($block['items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @elseif ($block['type'] === 'callout')
                <div class="callout">
                    @foreach ($block['text'] as $index => $paragraph)
                        {{-- The first line of a callout is its point; the rest
                             explain it. Bolding it is what makes the clause
                             findable by somebody skimming. --}}
                        <p>{!! $index === 0 ? '<strong>' . e($paragraph) . '</strong>' : e($paragraph) !!}</p>
                    @endforeach
                </div>
            @endif
        @endforeach
    @endforeach
@endsection

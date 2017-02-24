title: {{config('doc.title', 'Referência da API')}}

language_tabs:
- bash
- javascript

includes:

search: true

toc_footers:
@foreach(config('doc.footerLinks', []) as $link)
- <a href='{{$link['url']}}'>{{$link['description']}}</a>
@endforeach
{{--@if (config('app.env') === 'dev')--}}
- <a href='https://github.com/gilsonsouza/laradocgenerator'>Criado com <3</a>
{{--@endif--}}
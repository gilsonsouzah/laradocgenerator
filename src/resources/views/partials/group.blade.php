@if($group)
# {{ucfirst($group)}}
@endif
@foreach($routes as $parsedRoute)
{!! $parsedRoute['output']!!}
@endforeach
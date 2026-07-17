TMSC runtime exception

Application: {{ data_get($context, 'app.name') }}
Environment: {{ data_get($context, 'app.environment') }}
Reported: {{ data_get($context, 'app.reported_at') }}
Exception: {{ data_get($context, 'exception.class') }}
Message: {{ data_get($context, 'exception.message') }}
Location: {{ data_get($context, 'exception.file') }}:{{ data_get($context, 'exception.line') }}
@if (data_get($context, 'request'))
Request: {{ data_get($context, 'request.method') }} {{ data_get($context, 'request.url') }}
Route: {{ data_get($context, 'request.route') ?? 'unnamed' }}
Source IP: {{ data_get($context, 'request.ip') }}
@endif
@if (data_get($context, 'user'))
User: {{ data_get($context, 'user.email') }} ({{ data_get($context, 'user.id') }})
@endif

Trace:
@foreach (data_get($context, 'exception.trace', []) as $frame)
{{ $frame['file'] ?? '[internal]' }}:{{ $frame['line'] ?? '-' }} {{ $frame['class'] ?? '' }}{{ $frame['function'] ?? '' }}
@endforeach

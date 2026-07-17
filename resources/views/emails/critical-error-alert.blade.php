<h1>TMSC runtime exception</h1>

<p><strong>Application:</strong> {{ data_get($context, 'app.name') }}</p>
<p><strong>Environment:</strong> {{ data_get($context, 'app.environment') }}</p>
<p><strong>Reported:</strong> {{ data_get($context, 'app.reported_at') }}</p>
<p><strong>Exception:</strong> {{ data_get($context, 'exception.class') }}</p>
<p><strong>Message:</strong> {{ data_get($context, 'exception.message') }}</p>
<p><strong>Location:</strong> {{ data_get($context, 'exception.file') }}:{{ data_get($context, 'exception.line') }}</p>

@if (data_get($context, 'request'))
    <p><strong>Request:</strong> {{ data_get($context, 'request.method') }} {{ data_get($context, 'request.url') }}</p>
    <p><strong>Route:</strong> {{ data_get($context, 'request.route') ?? 'unnamed' }}</p>
    <p><strong>Source IP:</strong> {{ data_get($context, 'request.ip') }}</p>
@endif

@if (data_get($context, 'user'))
    <p><strong>User:</strong> {{ data_get($context, 'user.email') }} ({{ data_get($context, 'user.id') }})</p>
@endif

<h2>Trace</h2>
<pre>@foreach (data_get($context, 'exception.trace', []) as $frame){{ $frame['file'] ?? '[internal]' }}:{{ $frame['line'] ?? '-' }} {{ $frame['class'] ?? '' }}{{ $frame['function'] ?? '' }}
@endforeach</pre>

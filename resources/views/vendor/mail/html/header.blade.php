<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel' || trim($slot) === 'Divididinho')
<img src="{{ config('app.url') }}/images/logo.png" class="logo" alt="{{ config('app.name') }}" style="height: 50px;">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>

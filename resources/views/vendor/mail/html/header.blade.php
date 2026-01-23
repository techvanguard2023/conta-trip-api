<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel' || trim($slot) === 'Divididinho')
<img src="https://conta-trip.vercel.app/img/logo_divididinho.png" class="logo" alt="{{ config('app.name') }}" style="height: 80px; width: 280px;">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>

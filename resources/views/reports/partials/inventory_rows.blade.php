@forelse($data as $item)
    @php
        $isUnit = $item->isIndividual() && $item->assetUnits !== null && $item->assetUnits->isNotEmpty();
        $unitRows = $isUnit ? $item->assetUnits : collect([null]);
    @endphp
    @foreach($unitRows as $unit)
        <tr>
            <td>{{ $item->code }}</td>
            <td class="font-monospace">{{ $unit?->unit_inventory_number ?: ($item->inventory_number ?: '-') }}</td>
            <td class="font-monospace">{{ $unit?->serial_number ?: ($item->serial_number ?: '-') }}</td>
            <td>{{ $item->name }}</td>
            <td>{{ $item->category }}</td>
            <td>{{ $unit ? '1 '.$item->unit : $item->stock.' '.$item->unit }}</td>
            <td>{{ strtoupper(str_replace('_', ' ', $unit?->current_condition ?: $item->current_condition)) }}</td>
            <td>{{ $unit?->location?->name ?: $item->location?->name ?: '-' }}</td>
            <td>{{ $item->department ?: 'Umum' }}</td>
        </tr>
    @endforeach
@empty
    <tr><td colspan="9" class="text-center text-muted py-6">Tidak ada data untuk periode ini.</td></tr>
@endforelse
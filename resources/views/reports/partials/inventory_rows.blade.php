@forelse($data as $item)
    @php
        $isUnit = $item->isIndividual() && $item->assetUnits !== null && $item->assetUnits->isNotEmpty();
        $unitRows = $isUnit ? $item->assetUnits : collect([null]);
    @endphp
    @foreach($unitRows as $unit)
        @php
            $condition = $unit?->current_condition ?: $item->current_condition;
            $status = $unit?->current_status ?: $item->current_status;
            $unitNo = $unit?->unit_inventory_number ?: $item->inventory_number;
            $serial = $unit?->serial_number ?: $item->serial_number;
            $location = $unit?->location?->name ?: $item->location?->name;
        @endphp
        <tr>
            <td class="fw-bold text-gray-800">{{ $item->code }}</td>
            <td class="font-monospace">{!! $unitNo ? e($unitNo) : '<span class="empty-dash">—</span>' !!}</td>
            <td class="font-monospace">{!! $serial ? e($serial) : '<span class="empty-dash">—</span>' !!}</td>
            <td>{{ $item->name }}</td>
            <td>{{ $item->category ?: '—' }}</td>
            <td class="text-end">{{ $unit ? '1' : $item->stock }} {{ $item->unit }}</td>
            <td><x-condition-badge :condition="$condition" /></td>
            <td><x-unit-status-badge :status="$status" /></td>
            <td>{!! $location ? e($location) : '<span class="empty-dash">—</span>' !!}</td>
            <td>{{ \App\Support\Departments::display($item->department) }}</td>
        </tr>
    @endforeach
@empty
    <tr>
        <td colspan="10" class="text-center text-muted py-6">Tidak ada data inventaris.</td>
    </tr>
@endforelse

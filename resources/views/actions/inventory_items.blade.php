@php
    use App\Models\Inventory;

    /** @var Inventory $record */
    $record->loadMissing('items');
@endphp

<div class="custom-table-wrapper">
    <table class="custom-table">
        <thead>
        <tr>
            <th>Storage</th>
            <th>Storage floor</th>
            <th>Quantity</th>
        </tr>
        </thead>
        @foreach($record->items as $item)
            <tr>
                <td>{{ $item?->storageLocation?->name ?? '-' }}</td>

                <td>{{ $item?->storage_floor ?? '-' }}</td>

                <td>{{ $item->quantity }}</td>
            </tr>
        @endforeach
    </table>
</div>

@if ($getRecord() && $getRecord()->photo)
    <div style="margin-bottom: 15px;">
        <img
            src="{{ Storage::url($getRecord()->photo) }}"
            alt="Voertuigfoto"
            style="max-width: 300px; border-radius: 12px;"
        >
    </div>
@endif
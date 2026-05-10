@if ($getRecord() && $getRecord()->photo)
    <div style="margin-bottom: 15px;">
        <img
            src="{{ \Illuminate\Support\Facades\Storage::url($getRecord()->photo) }}"
            alt="{{ __('vehicles.preview.photo_alt') }}"
            style="max-width: 300px; border-radius: 12px;"
        >
    </div>
@endif

@php
    $savedLocation = $locationAsset?->vessel_id ? 'vessel:'.$locationAsset->vessel_id : (($locationAsset && filled($locationAsset->location) && $locationAsset->location !== 'Villa Shipping Office') ? 'current' : 'office');
@endphp
<label class="form-label" for="{{ $locationAsset ? 'edit-asset-location' : 'add-asset-location' }}">Asset Location</label>
<select id="{{ $locationAsset ? 'edit-asset-location' : 'add-asset-location' }}" name="asset_location" class="form-select" required>
    <option value="office" @selected(old('asset_location', $savedLocation) === 'office')>Villa Shipping Office</option>
    @if($savedLocation === 'current')<option value="current" @selected(old('asset_location', $savedLocation) === 'current')>{{ $locationAsset->location }} (current location)</option>@endif
    @foreach($assetVessels as $locationVessel)
        <option value="vessel:{{ $locationVessel->id }}" @selected(old('asset_location', $savedLocation) === 'vessel:'.$locationVessel->id)>{{ $locationVessel->vessel_name }}</option>
    @endforeach
</select>
<small class="text-muted">Office or vessel where this asset is assigned. Vessel options follow Vessel Management.</small>

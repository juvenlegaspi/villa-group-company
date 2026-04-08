<form action="{{ route('vessels.update', $vessel->id) }}" method="POST">
    @csrf
    @method('PUT')

    <input type="text" name="vessel_name" value="{{ $vessel->vessel_name }}" class="form-control" required>

    <input type="text" name="imo_number" value="{{ $vessel->imo_number }}" class="form-control">

    <input type="text" name="call_sign" value="{{ $vessel->call_sign }}" class="form-control">

    <button type="submit" class="btn btn-success mt-2">Update</button>
</form>
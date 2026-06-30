@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        @if (session('booking_success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('booking_success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <h2 >HYVE booking monitoring</h2>
                        {{--<h2 class="mb-2">Review and approve room bookings.</h2>--}}
                        <p class="text-muted mb-0">Click any booking record to open its full details, inspect the uploaded payment proof, and approve the request directly.</p>
                    </div>
                    <a href="{{ url('/hyve/layout') }}" class="btn btn-outline-primary">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i>
                        Open Room Layout
                    </a>
                </div>

                <form method="GET" action="{{ url('/hyve/booking') }}" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4 col-xl-3">
                        <label for="booking-search" class="form-label">Search</label>
                        <input id="booking-search" type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Reference, customer, email, phone">
                    </div>
                    <div class="col-12 col-md-3 col-xl-3">
                        <label for="booking-date" class="form-label">Booking date</label>
                        <input id="booking-date" type="date" name="date" value="{{ $selectedDate }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-3 col-xl-3">
                        <label for="booking-status" class="form-label">Header status</label>
                        <select id="booking-status" name="status" class="form-select">
                            <option value="">All statuses</option>
                            <option value="pending" @selected($selectedStatus === 'pending')>Pending</option>
                            <option value="confirmed" @selected($selectedStatus === 'confirmed')>Confirmed</option>
                            <option value="cancelled" @selected($selectedStatus === 'cancelled')>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 col-xl-3 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i>
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 p-4 pb-0">
                <div>
                    <h5 class="mb-1">Booking records</h5>
                    {{--<p class="text-muted mb-0">Detailed room schedules for {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('F d, Y') }}</p>--}}
                </div>
                <span class="badge rounded-pill text-bg-light">{{ $bookingRows->count() }} line(s)</span>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Customer</th>
                                <th>Room</th>
                                <th>Schedule</th>
                                <th>Payment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bookingRows as $row)
                                <tr class="js-booking-row" role="button" tabindex="0" data-booking='@json($row)'>
                                    <td>
                                        <div class="fw-semibold">{{ $row['reference_no'] }}</div>
                                        <small class="text-muted text-capitalize">{{ $row['booking_type'] }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $row['customer_name'] }}</div>
                                        <small class="d-block text-muted">{{ $row['email'] }}</small>
                                        <small class="d-block text-muted">{{ $row['phone'] }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $row['room_name'] }}</div>
                                        <small class="text-muted">{{ $row['guests'] }} guest(s)</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $row['booking_date'] }}</div>
                                        <small class="d-block text-muted">{{ $row['time_range'] }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">Php {{ number_format($row['total_amount'], 2) }}</div>
                                        <small class="d-block text-muted">DP: Php {{ number_format($row['downpayment_amount'], 2) }}</small>
                                        <small class="d-block text-muted">Balance: Php {{ number_format($row['balance_amount'], 2) }}</small>
                                        <small class="d-block text-muted">{{ $row['payment_method'] }} / {{ $row['payment_status'] }}</small>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $row['status'] === 'confirmed' ? 'text-bg-success' : 'text-bg-warning' }} text-uppercase">{{ $row['status'] }}</span>
                                        @if ($row['proof_name'])
                                            <div class="small text-muted mt-2">Proof uploaded</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">No booking records found for the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bookingDetailModal" tabindex="-1" aria-labelledby="bookingDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="bookingDetailModalLabel">Booking details</h5>
                        <p class="text-muted small mb-0">Review the request before approving it.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12 col-lg-7">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Reference</label>
                                    <input type="text" id="modal_reference_no" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <input type="text" id="modal_status" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Customer</label>
                                    <input type="text" id="modal_customer_name" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Booking type</label>
                                    <input type="text" id="modal_booking_type" class="form-control text-capitalize" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="text" id="modal_email" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" id="modal_phone" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Room</label>
                                    <input type="text" id="modal_room_name" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Guests</label>
                                    <input type="text" id="modal_guests" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Booking date</label>
                                    <input type="text" id="modal_booking_date" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Time range</label>
                                    <input type="text" id="modal_time_range" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment method</label>
                                    <input type="text" id="modal_payment_method" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment status</label>
                                    <input type="text" id="modal_payment_status" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Total</label>
                                    <input type="text" id="modal_total_amount" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Downpayment</label>
                                    <input type="text" id="modal_downpayment_amount" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Balance</label>
                                    <input type="text" id="modal_balance_amount" class="form-control" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Room description</label>
                                    <textarea id="modal_room_description" class="form-control" rows="2" readonly></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea id="modal_notes" class="form-control" rows="3" readonly></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-5">
                            <label class="form-label">Payment proof</label>
                            <div class="border rounded-4 p-3 bg-light h-100 d-flex flex-column gap-3">
                                <div id="modal_proof_empty" class="text-muted small">No uploaded proof available for this booking.</div>
                                <img id="modal_proof_image" src="" alt="Payment proof" class="img-fluid rounded-3 d-none border">
                                <div id="modal_proof_name" class="small text-muted"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                    <form id="approveBookingForm" method="POST" action="" class="m-0">
                        @csrf
                        <button id="approveBookingButton" type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i>
                            Approve Booking
                        </button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('bookingDetailModal');
            const bookingModal = new bootstrap.Modal(modalElement);
            const approveForm = document.getElementById('approveBookingForm');
            const approveButton = document.getElementById('approveBookingButton');
            const proofImage = document.getElementById('modal_proof_image');
            const proofEmpty = document.getElementById('modal_proof_empty');
            const proofName = document.getElementById('modal_proof_name');

            const fields = {
                reference_no: document.getElementById('modal_reference_no'),
                status: document.getElementById('modal_status'),
                customer_name: document.getElementById('modal_customer_name'),
                booking_type: document.getElementById('modal_booking_type'),
                email: document.getElementById('modal_email'),
                phone: document.getElementById('modal_phone'),
                room_name: document.getElementById('modal_room_name'),
                guests: document.getElementById('modal_guests'),
                booking_date: document.getElementById('modal_booking_date'),
                time_range: document.getElementById('modal_time_range'),
                payment_method: document.getElementById('modal_payment_method'),
                payment_status: document.getElementById('modal_payment_status'),
                total_amount: document.getElementById('modal_total_amount'),
                downpayment_amount: document.getElementById('modal_downpayment_amount'),
                balance_amount: document.getElementById('modal_balance_amount'),
                room_description: document.getElementById('modal_room_description'),
                notes: document.getElementById('modal_notes')
            };

            function openBookingModal(row) {
                const booking = JSON.parse(row.dataset.booking || '{}');

                fields.reference_no.value = booking.reference_no || 'N/A';
                fields.status.value = booking.status || 'pending';
                fields.customer_name.value = booking.customer_name || 'N/A';
                fields.booking_type.value = booking.booking_type || 'guest';
                fields.email.value = booking.email || 'N/A';
                fields.phone.value = booking.phone || 'N/A';
                fields.room_name.value = booking.room_name || 'N/A';
                fields.guests.value = booking.guests ?? 0;
                fields.booking_date.value = booking.booking_date || 'N/A';
                fields.time_range.value = booking.time_range || 'N/A';
                fields.payment_method.value = booking.payment_method || 'N/A';
                fields.payment_status.value = booking.payment_status || 'pending';
                fields.total_amount.value = 'Php ' + Number(booking.total_amount || 0).toFixed(2);
                fields.downpayment_amount.value = 'Php ' + Number(booking.downpayment_amount || 0).toFixed(2);
                fields.balance_amount.value = 'Php ' + Number(booking.balance_amount || 0).toFixed(2);
                fields.room_description.value = booking.room_description || 'No room description available.';
                fields.notes.value = booking.notes || 'No notes provided.';

                if (booking.proof_url) {
                    proofImage.src = booking.proof_url;
                    proofImage.classList.remove('d-none');
                    proofEmpty.classList.add('d-none');
                    proofName.textContent = booking.proof_name ? 'Uploaded file: ' + booking.proof_name : '';
                } else {
                    proofImage.src = '';
                    proofImage.classList.add('d-none');
                    proofEmpty.classList.remove('d-none');
                    proofName.textContent = booking.proof_name || '';
                }

                approveForm.action = '{{ url('/hyve/booking') }}/' + booking.header_id + '/approve';

                if (booking.can_approve) {
                    approveButton.classList.remove('d-none');
                    approveButton.disabled = false;
                } else {
                    approveButton.classList.add('d-none');
                    approveButton.disabled = true;
                }

                bookingModal.show();
            }

            document.querySelectorAll('.js-booking-row').forEach(function (row) {
                row.addEventListener('click', function () {
                    openBookingModal(this);
                });

                row.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openBookingModal(this);
                    }
                });
            });
        });
    </script>
@endsection

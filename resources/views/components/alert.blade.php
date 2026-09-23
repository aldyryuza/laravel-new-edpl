{{--
    Flash message dari session.

        <x-alert />
        session()->flash('success', 'Data tersimpan');
--}}
@foreach (['success' => 'bx-check-circle', 'info' => 'bx-info-circle', 'warning' => 'bx-error', 'error' => 'bx-x-circle'] as $type => $icon)
    @if (session()->has($type))
        <div class="alert alert-{{ $type === 'error' ? 'danger' : $type }} alert-dismissible d-flex align-items-center"
            role="alert">
            <i class="icon-base bx {{ $icon }} me-2"></i>
            <div>{{ session($type) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach

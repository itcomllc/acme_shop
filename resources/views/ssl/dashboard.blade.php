@extends('layouts.app')

@section('content')
    @livewire('ssl-dashboard')
@endsection

@push('scripts')
    <script>
        // Auto-hide flash messages
        setTimeout(function() {
            const messages = document.querySelectorAll('.bg-green-500');
            messages.forEach(function(message) {
                message.style.display = 'none';
            });
        }, 5000);
    </script>
@endpush

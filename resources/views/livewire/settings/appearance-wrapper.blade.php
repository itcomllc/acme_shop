@extends('layouts.app')

@section('content')
    @livewire('settings.appearance')
@endsection

@push('scripts')
<script>
    // テーママネージャとの連携
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Appearance wrapper loaded');
        
        // テーママネージャが利用可能になったらコンポーネントと連携
        window.whenThemeReady(() => {
            console.log('ThemeManager ready in appearance wrapper');
        });
    });
</script>
@endpush
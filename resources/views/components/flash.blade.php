@if (session('status'))
    <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
        {{ session('status') }}
    </div>
@endif

{{-- Errors thrown by actions (not tied to a form field). --}}
@foreach (['user', 'client', 'site', 'location'] as $key)
    @error($key)
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror
@endforeach

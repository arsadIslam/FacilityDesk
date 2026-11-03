@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null])

{{-- $options: [value => label] --}}
<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}"
            {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500']) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500']) }}>
    @if ($hint) <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p> @endif
    @error($name) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

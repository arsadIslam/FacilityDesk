@props(['name', 'label', 'checked' => false, 'value' => '1'])

<label class="inline-flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked)
           {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500']) }}>
    {{ $label }}
</label>

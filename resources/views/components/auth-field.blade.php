{{--
    One labelled input for the sign-in and sign-up forms, with its own error
    message so the field and the reason sit together.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'autocomplete' => null,
    'autofocus' => false,
])

<div class="space-y-1.5">
    <label for="{{ $name }}" class="block text-sm font-medium text-ink">{{ $label }}</label>

    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" required
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($autofocus) autofocus @endif
        value="{{ $type === 'password' ? '' : old($name) }}"
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        @class([
            'w-full rounded-xl border bg-panel px-3.5 py-2.5 text-sm text-ink transition placeholder:text-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-accent',
            'border-edge' => ! $errors->has($name),
            'border-red-300' => $errors->has($name),
        ])>

    @error($name)
        <p id="{{ $name }}-error" class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

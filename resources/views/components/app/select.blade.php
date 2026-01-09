@props([
    'label' => null,
    'name' => null,
    'id' => null, 
    'options' => [], // Em options na propriedade da tag colocar :options="['key' => 'text', ...]" 
    'value' => null,
    'required' => false, 
    'divClass' => 'mb-3', 
    'slot' => null
])

<div class="{{ $divClass }}">
    @if($label)
        <label for="{{ $id ?? $name }}" class="form-label">
            {{ $label }}
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $id ?? $name }}"
        {{ $attributes->class([
            'form-select',
            'is-invalid' => $errors->has($name),
            'is-valid'   => !$errors->has($name) && old($name),
        ]) }}
    >
        <option value="">Selecione...</option>

        @foreach($options as $key => $text)
            <option value="{{ $key }}"
                @selected(old($name, $value) == $key)
            >
                {{ $text }}
            </option>
        @endforeach
    </select>

    @if(trim($slot))
        <div class="form-text">
            {{ $slot }}
        </div>
    @endif

    @error($name)
        <div class="invalid-feedback">
            {{ $message }}
        </div>
    @enderror

</div>
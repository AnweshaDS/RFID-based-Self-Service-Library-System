@props(['name', 'id' => null, 'placeholder' => '', 'value' => ''])
<input
    type="text"
    name="{{ $name }}"
    id="{{ $id ?? $name }}"
    value="{{ $value }}"
    placeholder="{{ $placeholder }}"
    autocomplete="off"
    {{ $attributes }}
    style="flex:1; width:100%; background:rgba(255,255,255,0.1); color:#F6F7F5; border:1px solid rgba(246,247,245,0.25); border-radius:12px; padding:16px 18px; font-size:18px; min-height:56px; font-family:inherit; position:relative; z-index:999;"
>
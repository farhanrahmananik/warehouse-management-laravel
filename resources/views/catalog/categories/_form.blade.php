@php
    $category = $category ?? null;
    $isActive = old('is_active', $category?->is_active ?? true);
@endphp

<div class="mb-3">
    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
    <input
        type="text"
        name="name"
        id="name"
        value="{{ old('name', $category?->name) }}"
        class="form-control @error('name') is-invalid @enderror"
        required
    >
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="slug" class="form-label">Slug</label>
    <input
        type="text"
        name="slug"
        id="slug"
        value="{{ old('slug', $category?->slug) }}"
        class="form-control @error('slug') is-invalid @enderror"
    >
    <div class="form-text">Leave blank to auto-generate.</div>
    @error('slug')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="description" class="form-label">Description</label>
    <textarea
        name="description"
        id="description"
        rows="4"
        class="form-control @error('description') is-invalid @enderror"
    >{{ old('description', $category?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<input type="hidden" name="is_active" value="0">
<div class="form-check">
    <input
        type="checkbox"
        name="is_active"
        id="is_active"
        value="1"
        class="form-check-input @error('is_active') is-invalid @enderror"
        @checked((bool) $isActive)
    >
    <label for="is_active" class="form-check-label">Active</label>
    @error('is_active')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

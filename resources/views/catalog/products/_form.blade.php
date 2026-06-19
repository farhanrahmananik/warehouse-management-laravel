@php
    $product = $product ?? null;
    $isActive = old('is_active', $product?->is_active ?? true);
    $selectedCategoryId = old('category_id', $product?->category_id);
    $selectedUnitId = old('unit_id', $product?->unit_id);
@endphp

@if ($categories->isEmpty())
    <div class="alert alert-warning" role="alert">
        Please create an active category before creating a product.
    </div>
@endif

@if ($units->isEmpty())
    <div class="alert alert-warning" role="alert">
        No active units found. Unit seed/support data is required before creating products.
    </div>
@endif

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
        <select
            name="category_id"
            id="category_id"
            class="form-select @error('category_id') is-invalid @enderror"
            required
        >
            <option value="">Select category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        @error('category_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="unit_id" class="form-label">Unit <span class="text-danger">*</span></label>
        <select
            name="unit_id"
            id="unit_id"
            class="form-select @error('unit_id') is-invalid @enderror"
            required
        >
            <option value="">Select unit</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected((string) $selectedUnitId === (string) $unit->id)>
                    {{ $unit->name }}@if ($unit->short_name) ({{ $unit->short_name }})@endif
                </option>
            @endforeach
        </select>
        @error('unit_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input
            type="text"
            name="name"
            id="name"
            value="{{ old('name', $product?->name) }}"
            class="form-control @error('name') is-invalid @enderror"
            required
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="slug" class="form-label">Slug</label>
        <input
            type="text"
            name="slug"
            id="slug"
            value="{{ old('slug', $product?->slug) }}"
            class="form-control @error('slug') is-invalid @enderror"
        >
        <div class="form-text">Leave blank to auto-generate.</div>
        @error('slug')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
        <input
            type="text"
            name="sku"
            id="sku"
            value="{{ old('sku', $product?->sku) }}"
            class="form-control @error('sku') is-invalid @enderror"
            required
        >
        @error('sku')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="barcode" class="form-label">Barcode</label>
        <input
            type="text"
            name="barcode"
            id="barcode"
            value="{{ old('barcode', $product?->barcode) }}"
            class="form-control @error('barcode') is-invalid @enderror"
        >
        @error('barcode')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Description</label>
    <textarea
        name="description"
        id="description"
        rows="4"
        class="form-control @error('description') is-invalid @enderror"
    >{{ old('description', $product?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="purchase_price" class="form-label">Purchase Price</label>
        <input
            type="number"
            name="purchase_price"
            id="purchase_price"
            value="{{ old('purchase_price', $product?->purchase_price) }}"
            class="form-control @error('purchase_price') is-invalid @enderror"
            step="0.01"
            min="0"
        >
        @error('purchase_price')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="selling_price" class="form-label">Selling Price</label>
        <input
            type="number"
            name="selling_price"
            id="selling_price"
            value="{{ old('selling_price', $product?->selling_price) }}"
            class="form-control @error('selling_price') is-invalid @enderror"
            step="0.01"
            min="0"
        >
        @error('selling_price')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="reorder_level" class="form-label">Reorder Level</label>
        <input
            type="number"
            name="reorder_level"
            id="reorder_level"
            value="{{ old('reorder_level', $product?->reorder_level) }}"
            class="form-control @error('reorder_level') is-invalid @enderror"
            step="0.01"
            min="0"
        >
        @error('reorder_level')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
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

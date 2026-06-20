@php
    $warehouse = $warehouse ?? null;
    $isActive = old('is_active', $warehouse?->is_active ?? true);
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
        <input
            type="text"
            name="code"
            id="code"
            value="{{ old('code', $warehouse?->code) }}"
            class="form-control @error('code') is-invalid @enderror"
            required
        >
        @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input
            type="text"
            name="name"
            id="name"
            value="{{ old('name', $warehouse?->name) }}"
            class="form-control @error('name') is-invalid @enderror"
            required
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="contact_person" class="form-label">Contact Person</label>
        <input
            type="text"
            name="contact_person"
            id="contact_person"
            value="{{ old('contact_person', $warehouse?->contact_person) }}"
            class="form-control @error('contact_person') is-invalid @enderror"
        >
        @error('contact_person')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input
            type="text"
            name="phone"
            id="phone"
            value="{{ old('phone', $warehouse?->phone) }}"
            class="form-control @error('phone') is-invalid @enderror"
        >
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="email" class="form-label">Email</label>
        <input
            type="email"
            name="email"
            id="email"
            value="{{ old('email', $warehouse?->email) }}"
            class="form-control @error('email') is-invalid @enderror"
        >
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="city" class="form-label">City</label>
        <input
            type="text"
            name="city"
            id="city"
            value="{{ old('city', $warehouse?->city) }}"
            class="form-control @error('city') is-invalid @enderror"
        >
        @error('city')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label for="address" class="form-label">Address</label>
    <textarea
        name="address"
        id="address"
        rows="4"
        class="form-control @error('address') is-invalid @enderror"
    >{{ old('address', $warehouse?->address) }}</textarea>
    @error('address')
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

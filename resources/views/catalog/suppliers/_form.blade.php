@php
    $supplier = $supplier ?? null;
    $isActive = old('is_active', $supplier?->is_active ?? true);
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input
            type="text"
            name="name"
            id="name"
            value="{{ old('name', $supplier?->name) }}"
            class="form-control @error('name') is-invalid @enderror"
            required
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="company_name" class="form-label">Company Name</label>
        <input
            type="text"
            name="company_name"
            id="company_name"
            value="{{ old('company_name', $supplier?->company_name) }}"
            class="form-control @error('company_name') is-invalid @enderror"
        >
        @error('company_name')
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
            value="{{ old('email', $supplier?->email) }}"
            class="form-control @error('email') is-invalid @enderror"
        >
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input
            type="text"
            name="phone"
            id="phone"
            value="{{ old('phone', $supplier?->phone) }}"
            class="form-control @error('phone') is-invalid @enderror"
        >
        @error('phone')
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
    >{{ old('address', $supplier?->address) }}</textarea>
    @error('address')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="tax_number" class="form-label">Tax Number</label>
        <input
            type="text"
            name="tax_number"
            id="tax_number"
            value="{{ old('tax_number', $supplier?->tax_number) }}"
            class="form-control @error('tax_number') is-invalid @enderror"
        >
        @error('tax_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="opening_balance" class="form-label">Opening Balance</label>
        <input
            type="number"
            name="opening_balance"
            id="opening_balance"
            value="{{ old('opening_balance', $supplier?->opening_balance) }}"
            class="form-control @error('opening_balance') is-invalid @enderror"
            step="0.01"
            min="0"
        >
        @error('opening_balance')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="current_balance" class="form-label">Current Balance</label>
        <input
            type="number"
            name="current_balance"
            id="current_balance"
            value="{{ old('current_balance', $supplier?->current_balance) }}"
            class="form-control @error('current_balance') is-invalid @enderror"
            step="0.01"
            min="0"
        >
        @error('current_balance')
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

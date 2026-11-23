@php
    $state = $getState();
    $receipts = is_array($state) ? ($state['receipts'] ?? []) : [];
@endphp

<div class="expense-receipts">
    @if ($receipts === [])
        <p class="expense-receipts-empty">No Receipt Uploaded</p>
    @else
        <div class="expense-receipts-grid">
            @foreach ($receipts as $receipt)
                @php
                    $kind = in_array($receipt['kind'] ?? '', ['image', 'pdf'], true) ? $receipt['kind'] : 'file';
                    $filename = (string) ($receipt['filename'] ?? 'receipt');
                    $label = (string) ($receipt['label'] ?? $filename);
                    $previewUrl = (string) ($receipt['preview_url'] ?? '');
                    $downloadUrl = (string) ($receipt['download_url'] ?? '');
                @endphp
                <article class="expense-receipt-card">
                    <a class="expense-receipt-open" href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer">
                        {{ $filename }}
                    </a>
                    <p class="expense-receipt-label">{{ $label }}</p>
                    @if ($kind === 'image')
                        <a class="expense-receipt-preview" href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer">
                            <img src="{{ $previewUrl }}" alt="{{ $filename }}" class="expense-receipt-thumb" loading="lazy">
                        </a>
                    @elseif ($kind === 'pdf')
                        <iframe class="expense-receipt-pdf" src="{{ $previewUrl }}" title="{{ $filename }}"></iframe>
                    @endif
                    <a class="expense-receipt-download" href="{{ $downloadUrl }}">Download</a>
                </article>
            @endforeach
        </div>
    @endif
</div>

<style>
    .expense-receipts { color: #0f172a; }
    .expense-receipts-empty { margin: 0; font-size: 14px; font-weight: 600; }
    .expense-receipts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 16px;
    }
    .expense-receipt-card {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
    }
    .expense-receipt-open {
        color: #6d28d9;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        word-break: break-word;
    }
    .expense-receipt-label { margin: 0; color: #475569; font-size: 12px; }
    .expense-receipt-preview { display: block; }
    .expense-receipt-thumb {
        display: block;
        width: 100%;
        max-height: 220px;
        object-fit: contain;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .expense-receipt-pdf {
        width: 100%;
        height: 420px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
    }
    .expense-receipt-download {
        align-self: flex-start;
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 8px;
        background: #6d28d9;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
    }
</style>

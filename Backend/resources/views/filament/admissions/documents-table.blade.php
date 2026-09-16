@php
    use App\Filament\Resources\Admissions\Schemas\AdmissionInfolist;
    use Illuminate\Support\Collection;

    $documents = $getState();
    if ($documents instanceof Collection) {
        $rows = $documents->values();
    } elseif (is_array($documents)) {
        $rows = collect($documents)->values();
    } else {
        $rows = collect();
    }
@endphp

<div class="admission-docs-table-wrap">
    @if ($rows->isEmpty())
        <p class="admission-docs-empty">No documents uploaded.</p>
    @else
        <table class="admission-docs-table">
            <thead>
                <tr>
                    <th class="admission-docs-num">#</th>
                    <th>Document Type</th>
                    <th>File Name</th>
                    <th>File Type</th>
                    <th>Size</th>
                    <th class="admission-docs-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $document)
                    @php
                        $id = is_object($document) ? (int) ($document->id ?? 0) : (int) ($document['id'] ?? 0);
                        $type = is_object($document) ? ($document->document_type ?? null) : ($document['document_type'] ?? null);
                        $name = is_object($document) ? ($document->original_name ?? '-') : ($document['original_name'] ?? '-');
                        $mime = is_object($document) ? ($document->mime_type ?? null) : ($document['mime_type'] ?? null);
                        $size = is_object($document) ? ($document->size ?? 0) : ($document['size'] ?? 0);
                    @endphp
                    <tr>
                        <td class="admission-docs-num">{{ $index + 1 }}</td>
                        <td>{{ AdmissionInfolist::documentTypeLabel(is_string($type) ? $type : null) }}</td>
                        <td class="admission-docs-name" title="{{ $name }}">{{ $name ?: '-' }}</td>
                        <td>{{ AdmissionInfolist::fileTypeLabel(is_string($mime) ? $mime : null, is_string($name) ? $name : null) }}</td>
                        <td>{{ AdmissionInfolist::fileSizeLabel($size) }}</td>
                        <td class="admission-docs-actions">
                            @if ($id > 0)
                                <button
                                    type="button"
                                    class="admission-docs-btn"
                                    wire:click="previewAdmissionDocument({{ $id }})"
                                >View</button>
                                <button
                                    type="button"
                                    class="admission-docs-btn admission-docs-btn-primary"
                                    wire:click="downloadAdmissionDocument({{ $id }})"
                                >Download</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

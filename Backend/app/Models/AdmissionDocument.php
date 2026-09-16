<?php

namespace App\Models;

use App\Enums\AdmissionDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AdmissionDocument extends Model
{
    protected $fillable = [
        'admission_id',
        'document_type',
        'storage_key',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function typeEnum(): ?AdmissionDocumentType
    {
        return AdmissionDocumentType::tryFrom($this->document_type);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'document_type_label' => $this->typeEnum()?->label() ?? $this->document_type,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded' => true,
        ];
    }

    public function deleteStoredFile(): void
    {
        if (filled($this->path) && Storage::disk($this->disk ?: 'local')->exists($this->path)) {
            Storage::disk($this->disk ?: 'local')->delete($this->path);
        }
    }
}

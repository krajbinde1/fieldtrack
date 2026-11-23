<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ExpenseClaims\ExpenseClaimResource;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReceiptController extends Controller
{
    public function preview(Request $request, ExpenseClaim $expenseClaim, ExpenseItem $item): StreamedResponse
    {
        $item = $this->authorizedReceipt($request, $expenseClaim, $item);

        return Storage::disk($item->receipt_disk)->response(
            $item->receipt_path,
            $this->filename($item),
            $this->headers($item, inline: true),
            'inline',
        );
    }

    public function download(Request $request, ExpenseClaim $expenseClaim, ExpenseItem $item): StreamedResponse
    {
        $item = $this->authorizedReceipt($request, $expenseClaim, $item);

        return Storage::disk($item->receipt_disk)->download(
            $item->receipt_path,
            $this->filename($item),
            $this->headers($item, inline: false),
        );
    }

    private function authorizedReceipt(Request $request, ExpenseClaim $expenseClaim, ExpenseItem $item): ExpenseItem
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless($user->canAccessPanel(Filament::getPanel('admin')), 403);
        abort_unless(ExpenseClaimResource::canAccess(), 403);
        abort_unless(ExpenseClaimResource::canView($expenseClaim), 403);
        abort_unless(
            ExpenseClaimResource::getEloquentQuery()->whereKey($expenseClaim->getKey())->exists(),
            404,
        );
        abort_unless((int) $item->expense_claim_id === (int) $expenseClaim->id, 404);
        abort_unless(
            $item->hasReceipt() && Storage::disk($item->receipt_disk)->exists($item->receipt_path),
            404,
            'Receipt file is missing.',
        );

        return $item;
    }

    /**
     * @return array<string, string>
     */
    private function headers(ExpenseItem $item, bool $inline): array
    {
        $headers = [
            'Content-Type' => $item->receipt_mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($inline) {
            $headers['X-Frame-Options'] = 'SAMEORIGIN';
            $headers['Content-Security-Policy'] = "frame-ancestors 'self'";
        }

        return $headers;
    }

    private function filename(ExpenseItem $item): string
    {
        $name = str_replace(['\\', '/'], '-', trim((string) ($item->receipt_original_name ?: 'receipt')));

        return $name !== '' ? $name : 'receipt';
    }
}

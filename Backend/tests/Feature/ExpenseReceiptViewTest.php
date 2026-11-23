<?php

use App\Enums\ExpenseClaimStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ExpenseClaims\Pages\ViewExpenseClaim;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Models\User;
use App\Services\Expenses\ExpenseClaimService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function expenseReceiptViewClaim(array $org, string $number): ExpenseClaim
{
    return ExpenseClaim::query()->create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['centerA']->scheme_id,
        'created_by_user_id' => $org['userA']->id,
        'claim_number' => $number,
        'status' => ExpenseClaimStatus::PendingCenterManager->value,
        'payment_status' => 'unpaid',
        'current_level' => 1,
        'total_amount' => 350,
        'submitted_at' => now(),
    ]);
}

function expenseReceiptViewItem(ExpenseClaim $claim, array $overrides = []): ExpenseItem
{
    return ExpenseItem::query()->create(array_merge([
        'expense_claim_id' => $claim->id,
        'expense_date' => '2026-11-20',
        'type' => 'food',
        'amount' => 120,
        'description' => 'Field lunch',
        'sort_order' => 0,
    ], $overrides));
}

it('shows every stored receipt to an authorized viewer and serves preview and download', function () {
    Storage::fake('local');
    $org = seedOrg();
    $coordinator = User::query()->create([
        'name' => 'Receipt Coordinator',
        'email' => 'receipt-coord@test.local',
        'login_id' => 'receiptcoord',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    $claim = expenseReceiptViewClaim($org, 'EXP-RECEIPT-1');
    $image = UploadedFile::fake()->image('lunch-bill.jpg');
    $imagePath = $image->store('expenses/'.$claim->id, 'local');
    $imageItem = expenseReceiptViewItem($claim, [
        'description' => 'Field lunch',
        'receipt_disk' => 'local',
        'receipt_path' => $imagePath,
        'receipt_original_name' => 'lunch-bill.jpg',
        'receipt_mime' => 'image/jpeg',
        'sort_order' => 0,
    ]);
    $pdf = UploadedFile::fake()->create('fare-bill.pdf', 12, 'application/pdf');
    $pdfPath = $pdf->store('expenses/'.$claim->id, 'local');
    $pdfItem = expenseReceiptViewItem($claim, [
        'type' => 'travelling',
        'description' => 'Bus fare',
        'amount' => 230,
        'receipt_disk' => 'local',
        'receipt_path' => $pdfPath,
        'receipt_original_name' => 'fare-bill.pdf',
        'receipt_mime' => 'application/pdf',
        'sort_order' => 1,
    ]);
    expenseReceiptViewItem($claim, [
        'description' => 'No bill',
        'amount' => 10,
        'sort_order' => 2,
    ]);

    $imageBytes = Storage::disk('local')->get($imagePath);
    $pdfBytes = Storage::disk('local')->get($pdfPath);
    $imagePreview = route('admin.expense-receipts.preview', ['expenseClaim' => $claim->id, 'item' => $imageItem->id]);
    $imageDownload = route('admin.expense-receipts.download', ['expenseClaim' => $claim->id, 'item' => $imageItem->id]);
    $pdfPreview = route('admin.expense-receipts.preview', ['expenseClaim' => $claim->id, 'item' => $pdfItem->id]);
    $pdfDownload = route('admin.expense-receipts.download', ['expenseClaim' => $claim->id, 'item' => $pdfItem->id]);

    expect($imagePreview)->not->toContain('/storage/')
        ->and(Storage::disk('local')->exists($imagePath))->toBeTrue()
        ->and(Storage::disk('local')->exists($pdfPath))->toBeTrue();

    foreach ([$org['admin'], $org['director'], $coordinator, $org['centerManager']] as $viewer) {
        $preview = $this->actingAs($viewer)->get($imagePreview);
        $preview->assertOk();
        expect($preview->streamedContent())->toBe($imageBytes);
        expect(strtolower((string) $preview->headers->get('content-type')))->toContain('image/jpeg');
        expect(strtolower((string) $preview->headers->get('content-disposition')))
            ->toContain('inline')
            ->toContain('lunch-bill.jpg');
    }

    $download = $this->actingAs($org['admin'])->get($imageDownload);
    $download->assertOk();
    expect($download->streamedContent())->toBe($imageBytes);
    expect(strtolower((string) $download->headers->get('content-disposition')))
        ->toContain('attachment')
        ->toContain('lunch-bill.jpg');

    $pdfResponse = $this->actingAs($org['admin'])->get($pdfPreview);
    $pdfResponse->assertOk();
    expect($pdfResponse->streamedContent())->toBe($pdfBytes);
    expect(strtolower((string) $pdfResponse->headers->get('content-type')))->toContain('application/pdf');
    expect(strtolower((string) $pdfResponse->headers->get('content-disposition')))->toContain('inline');

    $pdfDownloadResponse = $this->actingAs($org['admin'])->get($pdfDownload);
    $pdfDownloadResponse->assertOk();
    expect($pdfDownloadResponse->streamedContent())->toBe($pdfBytes);
    expect(strtolower((string) $pdfDownloadResponse->headers->get('content-disposition')))
        ->toContain('attachment')
        ->toContain('fare-bill.pdf');

    $service = app(ExpenseClaimService::class);
    expect($service->canDecide($org['userA'], $claim))->toBeFalse()
        ->and($service->canDecide($org['centerManager'], $claim))->toBeTrue()
        ->and($service->canDecide($org['admin'], $claim))->toBeFalse();

    Livewire::actingAs($org['admin'])
        ->test(ViewExpenseClaim::class, ['record' => $claim->id])
        ->assertSee('lunch-bill.jpg')
        ->assertSee('fare-bill.pdf')
        ->assertSee('Field lunch')
        ->assertSee('Bus fare')
        ->assertSee('Download')
        ->assertSee('Approval timeline')
        ->assertSee('Center Manager Approval')
        ->assertDontSee('No Receipt Uploaded')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('<img')
        ->assertSeeHtml('<iframe')
        ->assertSeeHtml($imagePreview)
        ->assertSeeHtml($pdfPreview)
        ->assertSeeHtml($imageDownload)
        ->assertSeeHtml($pdfDownload);

    Livewire::actingAs($org['centerManager'])
        ->test(ViewExpenseClaim::class, ['record' => $claim->id])
        ->assertSee('lunch-bill.jpg')
        ->assertSee('fare-bill.pdf')
        ->assertActionVisible('approve')
        ->assertActionVisible('reject');
});

it('refuses receipt files to users who cannot open the expense view', function () {
    Storage::fake('local');
    $org = seedOrg();
    $other = User::query()->create([
        'name' => 'Other Manager',
        'email' => 'other-receipt@test.local',
        'login_id' => 'otherreceipt',
        'password' => Hash::make('CenterMgr@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $other->managedCenters()->attach($org['centerB']->id);

    $claim = expenseReceiptViewClaim($org, 'EXP-RECEIPT-2');
    $image = UploadedFile::fake()->image('private-bill.jpg');
    $path = $image->store('expenses/'.$claim->id, 'local');
    $item = expenseReceiptViewItem($claim, [
        'receipt_disk' => 'local',
        'receipt_path' => $path,
        'receipt_original_name' => 'private-bill.jpg',
        'receipt_mime' => 'image/jpeg',
    ]);
    $bytes = Storage::disk('local')->get($path);
    $preview = route('admin.expense-receipts.preview', ['expenseClaim' => $claim->id, 'item' => $item->id]);
    $download = route('admin.expense-receipts.download', ['expenseClaim' => $claim->id, 'item' => $item->id]);

    $this->get($preview)->assertRedirect('/admin/login');

    $staffPreview = $this->actingAs($org['userA'])->get($preview);
    $staffPreview->assertForbidden();
    expect($staffPreview->getContent())->not->toContain($bytes);
    $this->actingAs($org['userA'])->get($download)->assertForbidden();
    $this->actingAs($org['userA'])->get('/admin/expense-claims/'.$claim->id)->assertForbidden();

    $otherPreview = $this->actingAs($other)->get($preview);
    $otherPreview->assertNotFound();
    expect($otherPreview->getContent())->not->toContain($bytes);
    $this->actingAs($other)->get($download)->assertNotFound();
});

it('shows the empty receipt state when a claim has no uploaded file', function () {
    Storage::fake('local');
    $org = seedOrg();
    $claim = expenseReceiptViewClaim($org, 'EXP-RECEIPT-3');
    expenseReceiptViewItem($claim, [
        'description' => 'Taxi without a bill',
    ]);

    Livewire::actingAs($org['director'])
        ->test(ViewExpenseClaim::class, ['record' => $claim->id])
        ->assertSee('No Receipt Uploaded')
        ->assertSee('Approval timeline')
        ->assertSee('Center Manager Approval')
        ->assertDontSee('Taxi without a bill.jpg');
});

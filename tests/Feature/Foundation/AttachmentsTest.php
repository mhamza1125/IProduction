<?php

use App\Modules\Core\Livewire\Shared\ActivityHistory;
use App\Modules\Core\Livewire\Shared\Attachments;
use App\Modules\Core\Livewire\Shared\Notes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fixtures\TestDocument;

beforeEach(function () {
    Storage::fake('local');
    $this->doc = DB::transaction(fn () => TestDocument::create(['number' => 'TD-26-0001', 'document_date' => '2026-11-05']));
});

it('uploads, downloads and removes attachments with the right permissions', function () {
    $this->actingAs(userWith(['test_documents.view', 'test_documents.edit']));

    Livewire::test(Attachments::class, ['model' => $this->doc])
        ->set('files', [UploadedFile::fake()->create('vendor-invoice.pdf', 200, 'application/pdf')])
        ->call('upload')
        ->assertHasNoErrors()
        ->assertSee('vendor-invoice.pdf');

    $attachment = $this->doc->attachments()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $this->get(route('core.attachments.download', $attachment))->assertOk()->assertDownload('vendor-invoice.pdf');

    Livewire::test(Attachments::class, ['model' => $this->doc])->call('delete', $attachment->id);
    Storage::disk('local')->assertMissing($attachment->path);
});

it('rejects disallowed file types', function () {
    $this->actingAs(userWith(['test_documents.view', 'test_documents.edit']));

    Livewire::test(Attachments::class, ['model' => $this->doc])
        ->set('files', [UploadedFile::fake()->create('script.php', 1, 'text/x-php')])
        ->call('upload')
        ->assertHasErrors('files.0');
});

it('protects attachments with the record permissions', function () {
    $this->actingAs(userWith(['test_documents.view', 'test_documents.edit']));
    Livewire::test(Attachments::class, ['model' => $this->doc])
        ->set('files', [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
        ->call('upload');
    $attachment = $this->doc->attachments()->firstOrFail();

    $this->actingAs(userWith(['test_documents.view']));
    Livewire::test(Attachments::class, ['model' => $this->doc])
        ->set('files', [UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])
        ->call('upload')
        ->assertForbidden();

    $this->actingAs(userWith());
    $this->get(route('core.attachments.download', $attachment))->assertForbidden();
});

it('adds notes and shows activity history', function () {
    $this->actingAs(userWith(['test_documents.view']));

    Livewire::test(Notes::class, ['model' => $this->doc])
        ->set('body', 'Customer asked to hold dispatch until Friday.')
        ->call('add')
        ->assertSee('Customer asked to hold dispatch until Friday.');

    $this->doc->update(['title' => 'Changed']);

    Livewire::test(ActivityHistory::class, ['model' => $this->doc])->assertSee('Changed');
});

<?php

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Core\Services\Settings;
use App\Support\Documents\Approvals;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentStatus;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\TestDocument;

function draftDocument(string $date = '2026-11-05'): TestDocument
{
    return DB::transaction(fn () => TestDocument::create([
        'number' => app(DocumentNumber::class)->next('test_document', Carbon::parse($date)),
        'document_date' => $date,
        'title' => 'Sample',
        'created_by' => auth()->id(),
    ]));
}

beforeEach(fn () => $this->actingAs(admin()));

it('moves a document from draft to approved to posted and runs its effects once', function () {
    $doc = draftDocument();
    $workflow = app(DocumentWorkflow::class);

    approver(DocumentWorkflow::class)->approve($doc);
    expect($doc->status)->toBe(DocumentStatus::APPROVED)->and($doc->approved_by)->not->toBe(auth()->id())->and($doc->approved_by)->not->toBeNull();

    $workflow->post($doc, fn (TestDocument $locked) => $locked->increment('effects_run'));

    expect($doc->status)->toBe(DocumentStatus::POSTED)
        ->and($doc->effects_run)->toBe(1)
        ->and($doc->isPosted())->toBeTrue()
        ->and(Activity::where('subject_id', $doc->id)->where('event', 'posted')->exists())->toBeTrue();
});

it('cannot post twice (double click or retry)', function () {
    $doc = draftDocument();
    $workflow = app(DocumentWorkflow::class);
    approver(DocumentWorkflow::class)->approve($doc);

    $stale = TestDocument::find($doc->id); // a second request holding the old state
    $workflow->post($doc, fn (TestDocument $d) => $d->increment('effects_run'));

    expect(fn () => $workflow->post($stale, fn (TestDocument $d) => $d->increment('effects_run')))
        ->toThrow(DocumentException::class, 'already posted');

    expect($doc->fresh()->effects_run)->toBe(1);
});

it('requires approval before posting unless the setting is off', function () {
    $doc = draftDocument();
    $workflow = app(DocumentWorkflow::class);

    expect(fn () => $workflow->post($doc, fn () => null))->toThrow(DocumentException::class, 'cannot be posted');

    app(Approvals::class)->set('test_document', false);
    $workflow->post($doc, fn () => null);

    expect($doc->status)->toBe(DocumentStatus::POSTED);
});

it('saves nothing when an effect fails', function () {
    $doc = draftDocument();
    $workflow = app(DocumentWorkflow::class);
    approver(DocumentWorkflow::class)->approve($doc);

    expect(fn () => $workflow->post($doc, function (TestDocument $d) {
        $d->increment('effects_run');
        throw new DocumentException('Not enough stock for line 2.');
    }))->toThrow(DocumentException::class, 'Not enough stock');

    expect($doc->fresh())->status->toBe(DocumentStatus::APPROVED)->effects_run->toBe(0)->posted_at->toBeNull();
});

it('locks a document once it leaves draft', function () {
    $doc = draftDocument();
    $doc->update(['title' => 'Edited while draft']);

    approver(DocumentWorkflow::class)->approve($doc);

    expect(fn () => $doc->update(['title' => 'Edited after approval']))->toThrow(DocumentException::class, 'cannot be edited')
        ->and(fn () => $doc->delete())->toThrow(DocumentException::class, 'cannot be deleted');

    app(DocumentWorkflow::class)->returnToDraft($doc, 'Customer changed quantity');
    $doc->update(['title' => 'Edited after return to draft']);

    expect($doc->fresh()->title)->toBe('Edited after return to draft');
});

it('cancels only documents with no posted effect, with a reason', function () {
    $workflow = app(DocumentWorkflow::class);
    $doc = draftDocument();

    expect(fn () => $workflow->cancel($doc, ' '))->toThrow(DocumentException::class, 'reason');

    $workflow->cancel($doc, 'Duplicate entry');
    expect($doc->status)->toBe(DocumentStatus::CANCELLED)->and($doc->cancel_reason)->toBe('Duplicate entry');

    $posted = draftDocument();
    approver(DocumentWorkflow::class)->approve($posted);
    $workflow->post($posted, fn () => null);

    expect(fn () => $workflow->cancel($posted, 'Too late'))->toThrow(DocumentException::class, 'Reverse it');
});

it('blocks posting into a locked period', function () {
    app(Settings::class)->set('period_lock.date', '2026-10-31');
    $workflow = app(DocumentWorkflow::class);

    $this->actingAs(userWith(['test_documents.post', 'test_documents.approve']));
    $old = draftDocument('2026-10-15');
    approver(DocumentWorkflow::class)->approve($old);

    expect(fn () => $workflow->post($old, fn () => null))->toThrow(DocumentException::class, 'locked');

    $new = draftDocument('2026-11-01');
    approver(DocumentWorkflow::class)->approve($new);
    $workflow->post($new, fn () => null);
    expect($new->isPosted())->toBeTrue();
});

it('lets a user with the override permission post into a locked period with a reason', function () {
    app(Settings::class)->set('period_lock.date', '2026-10-31');
    $workflow = app(DocumentWorkflow::class);

    $this->actingAs(userWith(['period_lock.override']));
    $doc = draftDocument('2026-10-15');
    approver(DocumentWorkflow::class)->approve($doc);

    expect(fn () => $workflow->post($doc, fn () => null))->toThrow(DocumentException::class, 'Give a reason');

    $workflow->post($doc, fn () => null, 'Late supplier invoice approved by owner');

    expect($doc->isPosted())->toBeTrue()
        ->and(Activity::where('log_name', 'period_lock')->where('event', 'override')->first()->properties['reason'])
        ->toBe('Late supplier invoice approved by owner');
});

it('never lets the creator approve their own document, Super Admin included', function () {
    $doc = draftDocument();
    expect($doc->created_by)->toBe(auth()->id());

    expect(fn () => app(DocumentWorkflow::class)->approve($doc))->toThrow(DocumentException::class, 'another user must approve');
    expect($doc->fresh()->status)->toBe(DocumentStatus::DRAFT);

    // The creator is recorded even when the caller does not set it.
    $unset = DB::transaction(fn () => TestDocument::create(['number' => 'TD-X1', 'document_date' => '2026-11-05', 'title' => 'x']));
    expect($unset->created_by)->toBe(auth()->id())
        ->and(fn () => app(DocumentWorkflow::class)->approve($unset))->toThrow(DocumentException::class);

    $other = userWith(['test_documents.approve']);
    $this->actingAs($other);
    app(DocumentWorkflow::class)->approve($doc);
    expect($doc->fresh()->status)->toBe(DocumentStatus::APPROVED)->and($doc->fresh()->approved_by)->toBe($other->id);
});

it('does not check the creator when the document type needs no approval', function () {
    app(Approvals::class)->set('test_document', false);
    $doc = draftDocument();

    app(DocumentWorkflow::class)->approve($doc);
    expect($doc->fresh()->status)->toBe(DocumentStatus::APPROVED);
});

it('approves documents created inside an approved batch without a second approver', function () {
    $batch = draftDocument();
    approver(DocumentWorkflow::class)->approve($batch);
    $child = draftDocument();

    app(DocumentWorkflow::class)->approveAsPartOf($child, $batch);
    expect($child->fresh()->status)->toBe(DocumentStatus::APPROVED)
        ->and(Activity::where('subject_id', $child->id)->where('event', 'approved')->latest('id')->first()->properties['as_part_of'])->toBe($batch->number);
});

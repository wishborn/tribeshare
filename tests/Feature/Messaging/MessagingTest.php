<?php

use App\Enums\HatType;
use App\Enums\MessagingScope;
use App\Models\Asset;
use App\Models\Conversation;
use App\Models\Llc;
use App\Models\Region;
use App\Models\User;
use App\Services\Messaging\MessagingService;
use App\Services\Permissions\HatService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->messaging = app(MessagingService::class);
    $this->hats = app(HatService::class);
    $this->region = Region::factory()->create(['messaging_scope' => MessagingScope::LlcOnly]);
    $this->llc = Llc::factory()->for($this->region)->create();
});

/**
 * A member of the shared LLC.
 */
function llcMember(Llc $llc): User
{
    $user = User::factory()->create();
    app(HatService::class)->grant($user, HatType::LlcMember, $llc);

    return $user;
}

// --- Scope, enforced server-side -------------------------------------------

it('refuses a conversation the scope does not allow', function () {
    $mine = llcMember($this->llc);

    $otherLlc = Llc::factory()->for($this->region)->create();
    $theirs = llcMember($otherLlc);

    // The prototype enforced this in the messages page alone, so a direct
    // call reached anybody regardless of the configured policy.
    expect(fn () => $this->messaging->openDirect($mine, $theirs))
        ->toThrow(RuntimeException::class, 'LLC you belong to');
});

it('allows a conversation within the scope', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);

    $conversation = $this->messaging->openDirect($a, $b);

    expect($conversation->is_direct)->toBeTrue()
        ->and($conversation->members)->toHaveCount(2);
});

it('reads the scope from the region rather than the platform', function () {
    $mine = llcMember($this->llc);
    $theirs = llcMember(Llc::factory()->for($this->region)->create());

    expect(fn () => $this->messaging->openDirect($mine, $theirs))->toThrow(RuntimeException::class);

    // The region loosens its own policy; the platform default is untouched.
    $this->region->update(['messaging_scope' => MessagingScope::Regional]);

    expect($this->messaging->openDirect($mine->fresh(), $theirs->fresh()))
        ->toBeInstanceOf(Conversation::class);
});

it('falls back to the platform default when a region has not chosen', function () {
    $region = Region::factory()->create(['messaging_scope' => null]);
    $llc = Llc::factory()->for($region)->create();

    $mine = llcMember($llc);
    $theirs = llcMember(Llc::factory()->for($region)->create());

    // Null means "not chosen", never "no restriction".
    expect(fn () => $this->messaging->openDirect($mine, $theirs))->toThrow(RuntimeException::class);

    config()->set('tribeshare.messaging.default_scope', 'regional');

    expect($this->messaging->openDirect($mine->fresh(), $theirs->fresh()))
        ->toBeInstanceOf(Conversation::class);
});

it('binds the scope on send as well as on create', function () {
    $this->region->update(['messaging_scope' => MessagingScope::Regional]);

    $a = llcMember($this->llc);
    $b = llcMember(Llc::factory()->for($this->region)->create());

    $conversation = $this->messaging->openDirect($a, $b);
    $this->messaging->send($conversation, $a, 'Before.');

    // The region tightens after the thread exists. A conversation that was
    // legitimate yesterday is not a standing exemption.
    $this->region->update(['messaging_scope' => MessagingScope::LlcOnly]);

    expect(fn () => $this->messaging->send($conversation->fresh(), $a->fresh(), 'After.'))
        ->toThrow(RuntimeException::class);
});

it('lets an rcm reach anyone and be reached', function () {
    $rcm = User::factory()->create();
    $this->hats->grant($rcm, HatType::Rcm, $this->region);
    $member = llcMember($this->llc);

    // Fielding what members cannot resolve among themselves is the whole
    // role, so scope cannot be allowed to cut them off.
    expect($this->messaging->openDirect($member, $rcm))->toBeInstanceOf(Conversation::class);
});

it('applies the strictest policy when two regions disagree', function () {
    $strict = $this->region;
    $loose = Region::factory()->create(['messaging_scope' => MessagingScope::Anyone]);

    $a = llcMember($this->llc);
    $b = llcMember(Llc::factory()->for($strict)->create());

    // Both belong to the permissive region, but through DIFFERENT LLCs, so
    // the strict region's llc_only rule genuinely has nothing to match.
    $this->hats->grant($a, HatType::LlcMember, Llc::factory()->for($loose)->create());
    $this->hats->grant($b, HatType::LlcMember, Llc::factory()->for($loose)->create());

    // They share a permissive region and a strict one. Joining a permissive
    // region must not silently unlock messaging everywhere else.
    expect(fn () => $this->messaging->openDirect($a->fresh(), $b->fresh()))
        ->toThrow(RuntimeException::class);
});

it('allows pool members to message under a pool scope', function () {
    $this->region->update(['messaging_scope' => MessagingScope::PoolOnly]);

    $asset = Asset::factory()->for($this->llc)->create();
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $asset->poolMembers()->attach([$a->id, $b->id]);

    expect($this->messaging->openDirect($a, $b))->toBeInstanceOf(Conversation::class);

    $outsider = llcMember($this->llc);

    expect(fn () => $this->messaging->openDirect($a->fresh(), $outsider))
        ->toThrow(RuntimeException::class, 'asset pool');
});

// --- Participation ----------------------------------------------------------

it('refuses a message from someone not in the thread', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $intruder = llcMember($this->llc);

    $conversation = $this->messaging->openDirect($a, $b);

    // The prototype keyed sending on a conversation id alone, so any member
    // could post into any conversation.
    expect(fn () => $this->messaging->send($conversation, $intruder, 'Hello.'))
        ->toThrow(RuntimeException::class, 'not part of that conversation');
});

it('returns the existing thread rather than a second one', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);

    $first = $this->messaging->openDirect($a, $b);

    // Whichever of them starts it, the sorted key is the same.
    $second = $this->messaging->openDirect($b, $a);

    expect($second->id)->toBe($first->id)
        ->and(Conversation::count())->toBe(1);
});

it('adds a late arrival to the existing thread', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $c = llcMember($this->llc);

    $conversation = $this->messaging->openGroup($a, [$b], 'Planning');
    $this->messaging->addMembers($conversation, $a, [$c]);

    expect($conversation->refresh()->members)->toHaveCount(3);
});

it('archives a thread for one member only', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    $this->messaging->archive($conversation, $a);

    expect($conversation->participants()->where('user_id', $a->id)->first()->archived_at)->not->toBeNull()
        ->and($conversation->participants()->where('user_id', $b->id)->first()->archived_at)->toBeNull();
});

it('puts a thread away when the last participant leaves', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    $this->messaging->leave($conversation, $a);

    expect(Conversation::whereKey($conversation->id)->exists())->toBeTrue();

    $this->messaging->leave($conversation->fresh(), $b);

    // Archived for everyone who was ever in it, so it disappears rather
    // than lingering empty.
    expect(Conversation::whereKey($conversation->id)->exists())->toBeFalse();
});

// --- Messages ---------------------------------------------------------------

it('keeps a deleted message in its place in the thread', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    $message = $this->messaging->send($conversation, $a, 'Regrettable.');
    $this->messaging->deleteMessage($message, $a);

    $message->refresh();

    expect($message->body)->toBeNull()
        ->and($message->bodyWasDeleted())->toBeTrue()
        ->and($conversation->messages()->count())->toBe(1);
});

it('lets only the sender delete a message', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    $message = $this->messaging->send($conversation, $a, 'Mine.');

    // Not an owner, not a manager, not an RCM. A thread anyone senior can
    // edit is not a record of what was said.
    expect(fn () => $this->messaging->deleteMessage($message, $b))
        ->toThrow(RuntimeException::class, 'Only the sender');
});

it('counts a message as read by the person who wrote it', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    $message = $this->messaging->send($conversation, $a, 'Hello.');

    expect($message->isReadBy($a))->toBeTrue()
        ->and($message->isReadBy($b))->toBeFalse()
        ->and($this->messaging->unreadCount($b))->toBe(1)
        ->and($this->messaging->unreadCount($a))->toBe(0);
});

it('keeps a preview and a timestamp for the list', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    $this->messaging->send($conversation, $a, str_repeat('x', 200));

    $conversation->refresh();

    // Derived on write, never trusted from the client.
    expect(mb_strlen((string) $conversation->preview))->toBe(80)
        ->and($conversation->last_message_at)->not->toBeNull();
});

// --- Attachments ------------------------------------------------------------

it('stores an attachment against a message', function () {
    Storage::fake('local');

    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    $message = $this->messaging->send($conversation, $a, 'Here it is.', [
        UploadedFile::fake()->image('cabin.jpg'),
    ]);

    $attachment = $message->attachments()->sole();

    expect($attachment->original_name)->toBe('cabin.jpg')
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue();
});

it('refuses an attachment of a type not allowed', function () {
    Storage::fake('local');

    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    expect(fn () => $this->messaging->send($conversation, $a, null, [
        UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload'),
    ]))->toThrow(RuntimeException::class, 'cannot be attached');
});

it('refuses an attachment over the size ceiling', function () {
    Storage::fake('local');
    config()->set('tribeshare.messaging.attachments.max_bytes', 1024);

    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    expect(fn () => $this->messaging->send($conversation, $a, null, [
        UploadedFile::fake()->create('big.pdf', 50, 'application/pdf'),
    ]))->toThrow(RuntimeException::class, 'at most');
});

it('removes the files when a sender deletes their message', function () {
    Storage::fake('local');

    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    $message = $this->messaging->send($conversation, $a, 'Oops.', [
        UploadedFile::fake()->image('private.png'),
    ]);
    $path = $message->attachments()->sole()->path;

    $this->messaging->deleteMessage($message, $a);

    // The body going while the photograph stays would be a strange kind of
    // deletion.
    expect(Storage::disk('local')->exists($path))->toBeFalse()
        ->and($message->refresh()->attachments()->count())->toBe(0);
});

it('refuses a message with neither body nor attachment', function () {
    $a = llcMember($this->llc);
    $b = llcMember($this->llc);
    $conversation = $this->messaging->openDirect($a, $b);

    expect(fn () => $this->messaging->send($conversation, $a, null))
        ->toThrow(RuntimeException::class, 'body or an attachment');
});

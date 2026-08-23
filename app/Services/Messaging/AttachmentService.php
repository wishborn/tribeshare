<?php

namespace App\Services\Messaging;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Files on messages.
 *
 * The prototype recorded attachments without ever defining what they were —
 * a field on the message that nothing wrote and nothing read. These are real
 * files on a private disk.
 *
 * **Access control follows the conversation, never the URL.** Files are
 * stored outside the public root and served through a route that asks the
 * policy first, so knowing a path is not knowing a secret.
 */
class AttachmentService
{
    /**
     * Store a file against a message.
     *
     * @throws RuntimeException when the file is too large, of a type not
     *                          allowed, or one too many
     */
    public function attach(Message $message, UploadedFile $file): MessageAttachment
    {
        $this->assertAcceptable($message, $file);

        $disk = $this->disk();

        $path = $file->store("attachments/{$message->conversation_id}", $disk);

        if ($path === false) {
            throw new RuntimeException('That attachment could not be stored.');
        }

        return $message->attachments()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);
    }

    /**
     * Remove an attachment, file and all.
     *
     * Called when a sender deletes their message: the body going while the
     * photograph stays would be a strange kind of deletion.
     */
    public function remove(MessageAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        $attachment->delete();
    }

    /**
     * A readable stream for a file, once the caller has been authorised.
     *
     * @return resource|null
     */
    public function stream(MessageAttachment $attachment)
    {
        return Storage::disk($attachment->disk)->readStream($attachment->path);
    }

    public function exists(MessageAttachment $attachment): bool
    {
        return Storage::disk($attachment->disk)->exists($attachment->path);
    }

    private function assertAcceptable(Message $message, UploadedFile $file): void
    {
        $max = (int) config('tribeshare.messaging.attachments.max_bytes');

        if (($file->getSize() ?: 0) > $max) {
            $mb = round($max / 1024 / 1024, 1);

            throw new RuntimeException("An attachment may be at most {$mb}MB.");
        }

        /** @var array<int, string> $allowed */
        $allowed = config('tribeshare.messaging.attachments.allowed_mime_types', []);

        if ($allowed !== [] && ! in_array($file->getClientMimeType(), $allowed, true)) {
            throw new RuntimeException("Files of type {$file->getClientMimeType()} cannot be attached.");
        }

        $perMessage = (int) config('tribeshare.messaging.attachments.max_per_message');

        if ($message->attachments()->count() >= $perMessage) {
            throw new RuntimeException("A message may carry at most {$perMessage} attachments.");
        }
    }

    private function disk(): string
    {
        return (string) config('tribeshare.messaging.attachments.disk', 'local');
    }
}

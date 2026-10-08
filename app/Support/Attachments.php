<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Screenshots, error photos and log files on tickets: what is allowed, how it is validated and where it is kept.
 * To allow another file type, add it to IMAGE_TYPES (shown inline) or FILE_TYPES (download only).
 */
class Attachments
{
    public const MAX_FILES = 5;
    public const MAX_MB    = 10;

    /** Pictures: shown as thumbnails and opened in the browser. */
    public const IMAGE_TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    ];

    /** Everything else: always downloaded, never run by the browser. */
    public const FILE_TYPES = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain; charset=UTF-8',
        'log' => 'text/plain; charset=UTF-8',
        'csv' => 'text/csv; charset=UTF-8',
    ];

    public static function types(): array
    {
        return self::IMAGE_TYPES + self::FILE_TYPES;
    }

    /** For the file picker's "accept" attribute. */
    public static function accept(): string
    {
        return collect(array_keys(self::types()))->map(fn ($e) => '.' . $e)->implode(',');
    }

    /** "JPG, PNG, ..." for help text. */
    public static function label(): string
    {
        return collect(array_keys(self::types()))->reject(fn ($e) => $e === 'jpeg')->map(fn ($e) => strtoupper($e))->implode(', ');
    }

    public static function rules(): array
    {
        return [
            'files'   => ['nullable', 'array', 'max:' . self::MAX_FILES],
            'files.*' => [
                'bail', 'file', 'max:' . (self::MAX_MB * 1024), 'extensions:' . implode(',', array_keys(self::types())),
                // A picture must really be a picture, because pictures are shown in the page.
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());

                    if (isset(self::IMAGE_TYPES[$ext]) && ! str_starts_with((string) $value->getMimeType(), 'image/')) {
                        $fail($value->getClientOriginalName() . ' is not a valid picture.');
                    }
                },
            ],
        ];
    }

    public static function messages(): array
    {
        $max = self::MAX_FILES;
        $mb  = self::MAX_MB;

        return [
            'files.array'          => 'The attachments could not be read. Please choose your files again.',
            'files.max'            => "You can attach up to {$max} files at a time.",
            'files.*.file'         => 'One of the files could not be uploaded. Please try again.',
            'files.*.uploaded'     => "A file could not be uploaded. Each file can be at most {$mb} MB.",
            'files.*.max'          => "Each file can be at most {$mb} MB.",
            'files.*.extensions'   => 'Only pictures (' . implode(', ', array_map('strtoupper', array_keys(self::IMAGE_TYPES))) . ') and ' . implode(', ', array_map('strtoupper', array_keys(self::FILE_TYPES))) . ' files can be attached.',
        ];
    }

    /**
     * Save the uploaded files for a ticket (or for one reply on it). Files go to the private disk under a random
     * name, so nobody can guess a link and a file name can never run as code.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, TicketAttachment>
     */
    public static function store(Ticket $ticket, User $user, array $files, ?TicketComment $comment = null): Collection
    {
        return collect($files)->filter()->map(function (UploadedFile $file) use ($ticket, $user, $comment) {
            $ext  = strtolower($file->getClientOriginalExtension());
            $size = (int) $file->getSize();

            $clean = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $file->getClientOriginalName());
            $name  = trim((string) $clean) !== '' ? Str::limit(trim($clean), 150, '') : "attachment.{$ext}";

            $path = $file->storeAs(TicketAttachment::DIR . '/' . $ticket->id, Str::uuid() . '.' . $ext, TicketAttachment::DISK);

            if ($path === false) {
                throw new \RuntimeException('Could not save the attachment.');
            }

            return TicketAttachment::create([
                'ticket_id'         => $ticket->id,
                'ticket_comment_id' => $comment?->id,
                'user_id'           => $user->id,
                'original_name'     => $name,
                'path'              => $path,
                'mime'              => self::types()[$ext] ?? null, // our own mapping, never the browser's claim
                'size'              => $size,
            ]);
        })->values();
    }

    /** " (2 attachments)" for activity-log lines, or "" when there are none. */
    public static function note(Collection $saved): string
    {
        return $saved->isEmpty() ? '' : ' (' . $saved->count() . ' attachment' . ($saved->count() === 1 ? '' : 's') . ')';
    }
}

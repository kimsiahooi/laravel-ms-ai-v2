<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Support\FileNamer\FileNamer;

/**
 * Names every stored file after a fresh ULID instead of whatever the uploader called it:
 * `01JBQX7K2M9WZ4A6TCVN3EYHD8.jpg`.
 *
 * **This is what makes two uploads of `photo.jpg` safe.** The package's default keeps the
 * uploaded name and relies on each file having its own numbered directory; this app stores
 * files in one folder per owner, so the uniqueness has to live in the name. A ULID gives it
 * without a lookup: 80 bits of randomness under a millisecond timestamp, generated
 * monotonically within the same millisecond.
 *
 * A ULID rather than `Str::random()`, for one reason that shows up on a disk rather than in
 * a threat model: it sorts. `ls` on a folder of ULIDs is upload order, so the newest file
 * is the last line and a file from a bad afternoon is a range rather than a search. Random
 * names give the same collision safety and no order at all.
 *
 * **The original name is not lost.** medialibrary keeps it in `media.name` while
 * `media.file_name` holds what is on disk, so tracing a file back to what somebody uploaded
 * is one query and never a guess. It also means the uploaded name never reaches the
 * filesystem — no unicode, no 300-character names, no `../`, and no
 * `supplier-contract-Q3-margins.png` sitting in a directory listing.
 *
 * Only `originalFileName` is random, and that matters: it is called once, when the file is
 * added. The other two are recomputed every time a path is resolved — on read, on delete —
 * so they must be pure functions of the stored name, exactly as the package's default is.
 */
final class UlidFileNamer extends FileNamer
{
    /** Called once, at upload. The uploaded name is deliberately ignored. */
    public function originalFileName(string $fileName): string
    {
        return (string) Str::ulid();
    }

    /** Deterministic: `{ulid}-thumb`, resolved again on every read and delete. */
    public function conversionFileName(string $fileName, Conversion $conversion): string
    {
        return pathinfo($fileName, PATHINFO_FILENAME).'-'.$conversion->getName();
    }

    /** Deterministic, as above. */
    public function responsiveFileName(string $fileName): string
    {
        return pathinfo($fileName, PATHINFO_FILENAME);
    }
}

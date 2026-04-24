<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Stream an attachment file back to the requester. Requires auth +
     * any role — intentionally permissive for a small-district tool
     * where all admin-panel users are staff.
     */
    public function download(Request $request, Attachment $attachment): StreamedResponse|BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $hasAnyRole = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->isNotEmpty()
            : true;
        abort_unless($hasAnyRole, 403);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404, 'File missing on disk');

        return $disk->download($attachment->path, $attachment->original_name);
    }
}

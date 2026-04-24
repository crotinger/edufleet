<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $this->requireStaff($request);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404, 'File missing on disk');

        return $disk->download($attachment->path, $attachment->original_name);
    }

    /**
     * Assemble + stream a ZIP of every DQF-tagged attachment for a
     * driver, plus a README manifest. Filenames are prefixed with the
     * DQF component order so auditors see the binder in the standard
     * §391 sequence when they extract the ZIP.
     */
    public function driverBinder(Request $request, Driver $driver): BinaryFileResponse
    {
        $this->requireStaff($request);
        abort_unless($request->user()->can('view_driver'), 403);

        $components = Attachment::dqfComponents();
        $componentKeys = array_keys($components);

        $attachments = $driver->attachments()
            ->whereNotNull('dqf_component')
            ->with(['uploadedBy'])
            ->get();

        $tmpZip = tempnam(sys_get_temp_dir(), 'dqf-') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $driverSlug = Str::slug($driver->last_name . '-' . $driver->first_name);

        // README manifest at the top of the ZIP
        $zip->addFromString('README.txt', $this->buildManifest($driver, $attachments, $components));

        foreach ($componentKeys as $idx => $key) {
            $componentAttachments = $attachments->where('dqf_component', $key);
            if ($componentAttachments->isEmpty()) continue;

            $prefix = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $componentSlug = Str::slug($key);

            foreach ($componentAttachments as $a) {
                $disk = Storage::disk($a->disk);
                if (! $disk->exists($a->path)) continue;

                $ext = pathinfo($a->original_name, PATHINFO_EXTENSION) ?: 'bin';
                $base = pathinfo($a->original_name, PATHINFO_FILENAME);
                $innerName = "{$prefix}-{$componentSlug}/" . Str::slug($base, '_') . '.' . $ext;

                $zip->addFromString($innerName, $disk->get($a->path));
            }
        }

        $zip->close();

        $filename = "dqf-binder-{$driverSlug}-" . now()->format('Y-m-d') . '.zip';

        return response()->download($tmpZip, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function requireStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        $hasAnyRole = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->isNotEmpty()
            : true;
        abort_unless($hasAnyRole, 403);
    }

    /** @param \Illuminate\Support\Collection<int, Attachment> $attachments */
    private function buildManifest(Driver $driver, $attachments, array $components): string
    {
        $lines = [
            'Driver Qualification File',
            str_repeat('=', 60),
            "Driver: {$driver->last_name}, {$driver->first_name}",
            $driver->employee_id ? "Employee ID: {$driver->employee_id}" : null,
            $driver->license_number ? "CDL: class {$driver->license_class} #{$driver->license_number} ({$driver->license_state})" : null,
            $driver->license_expires_on ? "CDL expires: {$driver->license_expires_on->format('Y-m-d')}" : null,
            'Generated: ' . now()->format('Y-m-d H:i T'),
            '',
            'Per FMCSA 49 CFR §391 Subpart B, the following components make up the DQF.',
            'Files in this ZIP are organized in the standard §391 sequence.',
            '',
        ];

        foreach ($components as $key => $meta) {
            $files = $attachments->where('dqf_component', $key);
            $status = $files->isNotEmpty()
                ? '✓ PRESENT (' . $files->count() . ' file' . ($files->count() === 1 ? '' : 's') . ')'
                : ($meta['required'] ? '✗ MISSING' : '— (optional, not on file)');
            $lines[] = "[{$status}] {$meta['label']}";
            $lines[] = "    {$meta['cite']}";
            foreach ($files as $f) {
                $upload = $f->uploadedBy?->name ?? 'unknown';
                $when = $f->created_at?->format('Y-m-d');
                $lines[] = "    - {$f->original_name} (uploaded {$when} by {$upload})";
            }
            $lines[] = '';
        }

        return implode("\n", array_filter($lines, fn ($l) => $l !== null));
    }
}

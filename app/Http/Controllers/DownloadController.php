<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Export;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function export(Export $export, Request $request)
    {
        $request->validate(['inline' => 'nullable|boolean']);

        abort_unless($export->content, 404);

        if ($request->input('inline')) {
            return response($export->content)
                ->header('Content-Type', $export->mimetype)
                ->header('Content-Disposition', 'inline; filename="'.pathinfo($export->filename, PATHINFO_BASENAME).'"');
        }

        $export->increment('downloads');

        return response()->streamDownload(function () use ($export) {
            echo $export->content;
        }, pathinfo($export->filename, PATHINFO_BASENAME), [
            'Content-Type' => $export->mimetype ?? 'application/pdf',
        ]);
    }

    public function attachment(Attachment $attachment, Request $request)
    {
        $request->validate(['inline' => 'nullable|boolean']);

        abort_unless($attachment->content, 404);

        if ($request->input('inline')) {
            return response($attachment->content)
                ->header('Content-Type', $attachment->mimetype)
                ->header('Content-Disposition', 'inline; filename="'.pathinfo($attachment->filename, PATHINFO_BASENAME).'"');
        }

        return response()->streamDownload(function () use ($attachment) {
            echo $attachment->content;
        }, pathinfo($attachment->filename, PATHINFO_BASENAME));
    }
}

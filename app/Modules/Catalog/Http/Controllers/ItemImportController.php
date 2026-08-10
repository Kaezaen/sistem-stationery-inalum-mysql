<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\Uom;
use App\Modules\Catalog\Services\ItemImportService;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemImportController extends Controller
{
    public function __construct(private readonly ItemImportService $importer) {}

    public function create(): Response
    {
        $this->authorize('import', Item::class);

        return Inertia::render('Items/Import', [
            'requiredHeaders' => ItemImportService::REQUIRED_HEADERS,
            'categories' => Category::query()->orderBy('name')->pluck('name')->all(),
            'uoms' => Uom::query()->orderBy('code')->pluck('code')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('import', Item::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'update_existing' => ['boolean'],
        ], [
            'file.mimes' => 'Berkas harus berformat CSV. Simpan spreadsheet Anda sebagai CSV terlebih dahulu.',
        ]);

        $file = $request->file('file');

        if (! $file instanceof \Illuminate\Http\UploadedFile) {
            return back()->with('error', 'Berkas tidak terbaca.');
        }

        $result = $this->importer->import($file, $request->boolean('update_existing'));

        $summary = sprintf(
            '%d item ditambahkan, %d diperbarui, %d dilewati.',
            $result['imported'],
            $result['updated'],
            $result['skipped'],
        );

        if ($result['errors'] !== []) {
            return back()
                ->with('error', $summary.' Terdapat '.count($result['errors']).' baris bermasalah.')
                ->with('importErrors', $result['errors']);
        }

        return redirect()->route('items.index')->with('success', $summary);
    }

    /** Template CSV agar pengguna tidak menebak-nebak nama kolom. */
    public function template(): StreamedResponse
    {
        $this->authorize('import', Item::class);

        $headers = [...ItemImportService::REQUIRED_HEADERS, 'description', 'remark'];

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // BOM agar Excel membuka berkas sebagai UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, ['1709000002', 'BALL LINER, KENKO-SIZE 0,5-BLUE', 'Stationeries', 'EACH', '5', '10', '', '']);
            fclose($out);
        }, 'template-import-item.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

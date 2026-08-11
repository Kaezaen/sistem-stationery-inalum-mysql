<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Http\Controllers;

use App\Modules\Approval\Models\Approval;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Fulfillment\Services\HandoverService;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Models\RequestItem;
use App\Shared\Exceptions\BusinessRuleException;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\PaginatedPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Serah terima barang oleh PIC Gudang — wireframe 3.5.2.
 */
class HandoverController extends Controller
{
    public function __construct(
        private readonly HandoverService $handover,
        private readonly ApprovalService $approvals,
    ) {}

    /** Antrian "Menunggu Pengambilan Item". */
    public function index(HttpRequest $httpRequest): Response
    {
        $this->authorize('viewAny', Request::class);

        $tab = $httpRequest->string('tab')->toString() ?: 'pending';

        $requests = Request::query()
            ->with(['requester:id,name', 'department:id,code'])
            ->withCount('items')
            ->withStatus($tab === 'completed' ? RequestStatus::Completed : RequestStatus::ReadyForHandover)
            ->when($httpRequest->string('search')->toString() !== '', function ($q) use ($httpRequest): void {
                $term = '%'.$httpRequest->string('search')->toString().'%';
                $q->where('request_number', 'like', $term);
            })
            ->orderBy('request_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Fulfillment/Index', [
            'requests' => PaginatedPayload::make($requests, static fn (Request $r): array => [
                'id' => $r->id,
                'request_number' => $r->request_number,
                'requester' => $r->requester?->name,
                'department' => $r->department?->code,
                'request_date' => $r->request_date?->format('d/m/Y'),
                'items_count' => $r->items_count,
                'status_label' => $r->status->label(),
                'status_tone' => $r->status->tone(),
            ]),
            'tab' => $tab,
            'filters' => ['search' => $httpRequest->string('search')->toString()],
        ]);
    }

    /** Detail serah terima — wireframe 3.5.2. */
    public function show(HttpRequest $httpRequest, Request $request): Response
    {
        $this->authorize('view', $request);

        $request->load(['items.item.uom', 'requester', 'department']);

        return Inertia::render('Fulfillment/Show', [
            'request' => $this->detailPayload($request),
            'history' => $this->approvals->history($request)
                ->map(static fn (Approval $a): array => [
                    'id' => $a->id,
                    'level' => $a->approval_level,
                    'decision' => $a->status->value,
                    'decision_label' => $a->status->label(),
                    'approver' => $a->approver?->name,
                    'role' => $a->approver_role,
                    'date' => $a->approval_date?->format('d/m/Y H:i'),
                    'notes' => $a->rejection_notes,
                    'superseded' => $a->is_superseded,
                ])->all(),
            'canHandover' => $httpRequest->user()?->can('handover', $request) ?? false,
        ]);
    }

    public function store(HttpRequest $httpRequest, Request $request): RedirectResponse
    {
        $this->authorize('handover', $request);

        $validated = $httpRequest->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['integer', 'min:0'],
        ], [
            'quantities.*.min' => 'Jumlah yang diserahkan tidak boleh negatif.',
        ]);

        /** @var array<int, int>|null $quantities */
        $quantities = $validated['quantities'] ?? null;

        try {
            $this->handover->handover($request, $httpRequest->user(), $quantities);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('handover.receipt', $request->id)
            ->with('success', "Barang untuk {$request->request_number} berhasil diserahkan.");
    }

    /** Bukti serah terima — halaman siap cetak. */
    public function receipt(Request $request): Response
    {
        $this->authorize('view', $request);

        $request->load(['items.item.uom', 'requester', 'department']);

        return Inertia::render('Fulfillment/Receipt', [
            'request' => $this->detailPayload($request),
            'issuedAt' => $request->completed_at?->format('d/m/Y H:i'),
            'company' => 'PT Indonesia Asahan Aluminium',
        ]);
    }

    /** @return array<string, mixed> */
    private function detailPayload(Request $request): array
    {
        return [
            'id' => $request->id,
            'request_number' => $request->request_number,
            'requester' => $request->requester?->name,
            'department' => $request->department?->code,
            'request_date' => $request->request_date?->format('d/m/Y'),
            'notes' => $request->notes,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_tone' => $request->status->tone(),
            'items' => $request->items->map(static fn (RequestItem $line): array => [
                'id' => $line->id,
                'item_code' => $line->item->item_code ?? '',
                'item_name' => $line->item->item_name ?? '',
                'uom' => $line->item->uom->code ?? null,
                'physical_stock' => $line->item->stock_quantity ?? 0,
                'quantity_requested' => $line->quantity_requested,
                'quantity_approved' => $line->quantity_approved,
                'quantity_actual' => $line->quantity_actual,
                'remark' => $line->remark,
                'status_label' => $line->status->label(),
                'status_tone' => $line->status->tone(),
            ])->all(),
        ];
    }
}

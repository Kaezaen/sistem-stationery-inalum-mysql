<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Http\Controllers;

use App\Modules\Approval\Models\Approval;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Http\Requests\StoreRequestRequest;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Models\RequestItem;
use App\Modules\Requisition\Services\RequestApprovalService;
use App\Modules\Requisition\Services\RequestService;
use App\Shared\Exceptions\BusinessRuleException;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\PaginatedPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pembuatan dan pemantauan request oleh requester.
 *
 * CATATAN: model domain bernama Request, sehingga request HTTP diimpor dengan
 * alias HttpRequest agar keduanya tidak tertukar.
 */
class RequestController extends Controller
{
    public function __construct(
        private readonly RequestService $requests,
        private readonly RequestApprovalService $approvalService,
        private readonly ApprovalService $approvals,
    ) {}

    /** Halaman "Data Request Items". */
    public function index(HttpRequest $httpRequest): Response
    {
        $this->authorize('viewAny', Request::class);

        $user = $httpRequest->user();
        $status = $httpRequest->string('status')->toString();

        $requests = Request::query()
            ->with(['requester:id,name', 'department:id,name'])
            ->withCount('items')
            // Requester hanya melihat miliknya; pemegang view.all melihat semua.
            ->when(
                ! $user->can('request.view.all'),
                fn ($q) => $q->where('requester_id', $user->id),
            )
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Requests/Index', [
            'requests' => PaginatedPayload::make($requests, fn (Request $r): array => $this->rowPayload($r)),
            'filters' => ['status' => $status],
            'statuses' => RequestStatus::options(),
        ]);
    }

    /** Halaman "Request Items" — wireframe 3.1.2. */
    public function create(): Response
    {
        $this->authorize('create', Request::class);

        return Inertia::render('Requests/Create', [
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function store(StoreRequestRequest $httpRequest): RedirectResponse
    {
        $data = $httpRequest->validated();

        try {
            $request = $this->requests->create($httpRequest->user(), $data['items'], $data['notes'] ?? null);

            // Wireframe 3.1.2 hanya punya tombol "Submit Request" — dokumen
            // langsung masuk antrian Pimpinan, tidak berhenti sebagai draft.
            $this->requests->submit($request);
        } catch (BusinessRuleException $e) {
            throw $e->toValidationException('items');
        }

        return redirect()
            ->route('requests.index')
            ->with('success', "Request {$request->request_number} berhasil diajukan.");
    }

    public function show(HttpRequest $httpRequest, Request $request): Response
    {
        $this->authorize('view', $request);

        return Inertia::render('Requests/Show', [
            'request' => $this->detailPayload($request),
            'history' => $this->historyPayload($request),
            'canRevise' => $httpRequest->user()?->can('revise', $request) ?? false,
            'canCancel' => $httpRequest->user()?->can('cancel', $request) ?? false,
        ]);
    }

    /** Layar revisi setelah ditolak — Bab 3.6 (requester). */
    public function revise(Request $request): Response
    {
        $this->authorize('revise', $request);

        return Inertia::render('Requests/Revise', [
            'request' => $this->detailPayload($request),
            'history' => $this->historyPayload($request),
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function storeRevision(StoreRequestRequest $httpRequest, Request $request): RedirectResponse
    {
        $this->authorize('revise', $request);

        $data = $httpRequest->validated();

        try {
            $this->requests->updateLines($request, $data['items'], $data['notes'] ?? null);
            $this->approvalService->revise($request, $httpRequest->user());
        } catch (BusinessRuleException $e) {
            throw $e->toValidationException('items');
        }

        return redirect()
            ->route('requests.index')
            ->with('success', "Request {$request->request_number} berhasil diajukan ulang.");
    }

    public function submit(Request $request): RedirectResponse
    {
        $this->authorize('submit', $request);

        try {
            $this->requests->submit($request);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('requests.index')->with('success', 'Request diajukan.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->authorize('cancel', $request);

        try {
            $this->requests->cancel($request);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('requests.index')->with('success', 'Request dibatalkan.');
    }

    /** @return array<string, mixed> */
    private function rowPayload(Request $request): array
    {
        return [
            'id' => $request->id,
            'request_number' => $request->request_number,
            'requester' => $request->requester?->name,
            'department' => $request->department?->name,
            'request_date' => $request->request_date?->format('d/m/Y'),
            'items_count' => $request->items_count,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_tone' => $request->status->tone(),
        ];
    }

    /** @return array<string, mixed> */
    private function detailPayload(Request $request): array
    {
        $request->load(['items.item.uom', 'items.item.category', 'requester', 'department']);

        return [
            ...$this->rowPayload($request),
            'notes' => $request->notes,
            'revision_count' => $request->revision_count,
            'items' => $request->items->map(static fn (RequestItem $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item->item_code ?? '',
                'item_name' => $line->item->item_name ?? '',
                'category' => $line->item->category->name ?? null,
                'uom' => $line->item->uom->code ?? null,
                'available_stock' => $line->item?->availableQuantity() ?? 0,
                'quantity_requested' => $line->quantity_requested,
                'quantity_approved' => $line->quantity_approved,
                'quantity_actual' => $line->quantity_actual,
                'remark' => $line->remark,
                'status_label' => $line->status->label(),
                'status_tone' => $line->status->tone(),
            ])->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function historyPayload(Request $request): array
    {
        return $this->approvals->history($request)
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
            ])->all();
    }
}

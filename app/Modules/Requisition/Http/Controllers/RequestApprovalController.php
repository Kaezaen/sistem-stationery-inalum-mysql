<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Http\Controllers;

use App\Modules\Approval\Models\Approval;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Http\Requests\RejectRequestRequest;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Models\RequestItem;
use App\Modules\Requisition\Services\RequestApprovalService;
use App\Shared\Exceptions\BusinessRuleException;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\PaginatedPayload;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Layar "Verify Request Items" untuk ketiga level approval.
 *
 * Satu controller melayani tiga level karena antrian dan detailnya identik —
 * yang berbeda hanya kewenangan dan bentuk keputusannya. Level yang berlaku
 * ditentukan STATUS DOKUMEN, tidak pernah dari input pengguna.
 */
class RequestApprovalController extends Controller
{
    public function __construct(
        private readonly RequestApprovalService $service,
        private readonly ApprovalService $approvals,
    ) {}

    /** Antrian approval — wireframe 3.2.2. */
    public function index(HttpRequest $httpRequest): Response
    {
        $this->authorize('viewAny', Request::class);

        $user = $httpRequest->user();
        $tab = $httpRequest->string('tab')->toString() ?: 'pending';

        $requests = Request::query()
            ->with(['requester:id,name', 'department:id,name,code'])
            ->withCount('items')
            ->when($tab === 'pending', fn ($q) => $q->whereIn('status', $this->pendingStatusesFor($user)))
            ->when($tab === 'approved', fn ($q) => $q->whereIn('status', [
                RequestStatus::ReadyForHandover->value,
                RequestStatus::Completed->value,
            ]))
            ->when($tab === 'rejected', fn ($q) => $q->whereIn('status', [
                RequestStatus::RejectedSupervisor->value,
                RequestStatus::RejectedStationery->value,
                RequestStatus::RejectedSga->value,
            ]))
            // Pimpinan hanya melihat request bawahan langsungnya. Tanpa saringan
            // ini, antriannya akan memuat request seluruh perusahaan meski
            // Policy tetap menolak saat ia mencoba menyetujui.
            ->when(
                ! $user->can('request.view.all'),
                fn ($q) => $q->whereHas('requester', fn ($sub) => $sub->where('manager_id', $user->id)),
            )
            ->orderBy('request_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Requests/Verify/Index', [
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
        ]);
    }

    /**
     * Detail request untuk diputuskan.
     *
     * Bentuk layarnya berbeda per level — hanya level 2 yang menampilkan input
     * kuantitas. Yang menentukan adalah status dokumen (wireframe 3.2.3, 3.3.2,
     * 3.4.2 memang berbeda satu sama lain).
     */
    public function show(HttpRequest $httpRequest, Request $request): Response
    {
        $this->authorize('view', $request);

        $user = $httpRequest->user();
        $request->load(['items.item.uom', 'requester', 'department']);

        return Inertia::render('Requests/Verify/Show', [
            'request' => [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'requester' => $request->requester?->name,
                'department' => $request->department?->code,
                'request_date' => $request->request_date?->format('d/m/Y'),
                'notes' => $request->notes,
                'revision_count' => $request->revision_count,
                'status' => $request->status->value,
                'status_label' => $request->status->label(),
                'status_tone' => $request->status->tone(),
                'items' => $request->items->map(static fn (RequestItem $line): array => [
                    'id' => $line->id,
                    'item_code' => $line->item->item_code ?? '',
                    'item_name' => $line->item->item_name ?? '',
                    'category' => $line->item->category->name ?? null,
                    'uom' => $line->item->uom->code ?? null,
                    'available_stock' => $line->item?->availableQuantity() ?? 0,
                    'quantity_requested' => $line->quantity_requested,
                    'quantity_approved' => $line->quantity_approved,
                    'remark' => $line->remark,
                ])->all(),
            ],
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
            // Level 2 adalah satu-satunya yang menampilkan input kuantitas.
            'mode' => match ($request->status) {
                RequestStatus::PendingSupervisor => 'l1',
                RequestStatus::PendingStationery => 'l2',
                RequestStatus::PendingSga => 'l3',
                default => 'readonly',
            },
            'canDecide' => $this->canDecide($user, $request),
        ]);
    }

    /**
     * Menyetujui — level ditentukan status dokumen.
     *
     * Pemeriksaan kewenangan tetap dilakukan Policy per level; method ini hanya
     * mengarahkan ke service yang tepat.
     */
    public function approve(HttpRequest $httpRequest, Request $request): RedirectResponse
    {
        /*
         * SENGAJA memakai HttpRequest biasa, bukan FormRequest.
         *
         * Union type FormRequest|HttpRequest membuat Laravel selalu me-resolve ke
         * FormRequest-nya, sehingga authorize() milik level 2 ikut berjalan saat
         * yang menyetujui adalah approver level 1 atau 3 — dan mereka selalu
         * ditolak. Validasi input level 2 karena itu dilakukan di approveL2().
         */
        $user = $httpRequest->user();

        try {
            match ($request->status) {
                RequestStatus::PendingSupervisor => $this->approveL1($user, $request),
                RequestStatus::PendingStationery => $this->approveL2($httpRequest, $user, $request),
                RequestStatus::PendingSga => $this->approveL3($user, $request),
                default => abort(403),
            };
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('requests.verify.index')
            ->with('success', "Request {$request->request_number} berhasil diproses.");
    }

    public function reject(RejectRequestRequest $httpRequest, Request $request): RedirectResponse
    {
        $user = $httpRequest->user();
        $reason = $httpRequest->string('rejection_notes')->toString();

        try {
            match ($request->status) {
                RequestStatus::PendingSupervisor => $this->rejectAt(
                    fn () => $this->service->rejectBySupervisor($request, $user, $reason),
                    $user, $request, 'approveL1',
                ),
                RequestStatus::PendingStationery => $this->rejectAt(
                    fn () => $this->service->rejectByStationery($request, $user, $reason),
                    $user, $request, 'approveL2',
                ),
                RequestStatus::PendingSga => $this->rejectAt(
                    fn () => $this->service->rejectBySga($request, $user, $reason),
                    $user, $request, 'approveL3',
                ),
                default => abort(403),
            };
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('requests.verify.index')
            ->with('success', "Request {$request->request_number} ditolak.");
    }

    private function approveL1(mixed $user, Request $request): void
    {
        $this->authorize('approveL1', $request);
        $this->service->approveBySupervisor($request, $user);
    }

    private function approveL2(HttpRequest $httpRequest, mixed $user, Request $request): void
    {
        // Otorisasi lebih dulu, baru validasi — supaya approver yang tidak
        // berwenang menerima 403, bukan bocoran soal bentuk input yang diharapkan.
        $this->authorize('approveL2', $request);

        $validated = $httpRequest->validate([
            'decisions' => ['required', 'array', 'min:1'],
            // Batas atas terhadap quantity_requested diperiksa service, karena
            // hanya di sana kuantitas asli setiap baris diketahui.
            'decisions.*.quantity' => ['required', 'integer', 'min:0'],
            'decisions.*.remark' => ['nullable', 'string', 'max:500'],
        ], [
            'decisions.required' => 'Isi jumlah yang disetujui untuk setiap item.',
            'decisions.*.quantity.min' => 'Jumlah yang disetujui tidak boleh negatif.',
        ]);

        /** @var array<int, array{quantity: int, remark?: string|null}> $decisions */
        $decisions = $validated['decisions'];

        $this->service->approveByStationery($request, $user, $decisions);
    }

    private function approveL3(mixed $user, Request $request): void
    {
        $this->authorize('approveL3', $request);
        $this->service->approveBySga($request, $user);
    }

    private function rejectAt(Closure $action, mixed $user, Request $request, string $ability): void
    {
        $this->authorize($ability, $request);
        $action();
    }

    private function canDecide(mixed $user, Request $request): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->can('approveL1', $request)
            || $user->can('approveL2', $request)
            || $user->can('approveL3', $request);
    }

    /**
     * Status yang relevan pada tab Pending untuk user tertentu.
     *
     * Antrian hanya memuat level yang benar-benar menjadi kewenangannya, supaya
     * approver tidak melihat dokumen yang bukan gilirannya.
     *
     * @return list<string>
     */
    private function pendingStatusesFor(mixed $user): array
    {
        $statuses = [];

        if ($user->can('request.approve.l1')) {
            $statuses[] = RequestStatus::PendingSupervisor->value;
        }

        if ($user->can('request.approve.l2')) {
            $statuses[] = RequestStatus::PendingStationery->value;
            // PIC Stationery juga yang merevisi setelah ditolak SGA (Bab 3.7).
            $statuses[] = RequestStatus::RejectedSga->value;
        }

        if ($user->can('request.approve.l3')) {
            $statuses[] = RequestStatus::PendingSga->value;
        }

        return $statuses === [] ? ['__none__'] : $statuses;
    }
}

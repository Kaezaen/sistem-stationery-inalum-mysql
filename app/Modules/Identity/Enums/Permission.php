<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * Seluruh permission sistem — turunan langsung matriks §5.1 Architecture Blueprint.
 *
 * PENTING: permission hanya menjawab "apakah role ini boleh mengakses fitur X".
 * Pertanyaan kontekstual — "apakah user INI atasan dari requester request INI, dan
 * apakah status request tepat" — dijawab Laravel Policy, bukan di sini (§5.2).
 *
 * Tanpa lapis Policy, seorang Pimpinan dari seksi lain dapat menyetujui request
 * yang bukan wewenangnya. Permission saja TIDAK cukup untuk keamanan.
 */
enum Permission: string
{
    // Request
    case RequestCreate = 'request.create';
    case RequestViewOwn = 'request.view.own';
    case RequestViewSubordinate = 'request.view.subordinate';
    case RequestViewAll = 'request.view.all';
    case RequestUpdate = 'request.update';
    case RequestSubmit = 'request.submit';
    case RequestCancel = 'request.cancel';

    // Approval request
    case RequestApproveL1 = 'request.approve.l1';
    case RequestApproveL2 = 'request.approve.l2';
    case RequestApproveL3 = 'request.approve.l3';
    case RequestHandover = 'request.handover';

    // Master data
    case ItemView = 'item.view';
    case ItemCreate = 'item.create';
    case ItemUpdate = 'item.update';
    case ItemDelete = 'item.delete';
    case CategoryManage = 'category.manage';
    case UomManage = 'uom.manage';

    // Purchasing
    case PurchaseCreate = 'purchase.create';
    case PurchaseView = 'purchase.view';
    case PurchaseUpdate = 'purchase.update';
    case PurchaseVerify = 'purchase.verify';

    // Inventory
    case InventoryView = 'inventory.view';
    case InventoryAdjust = 'inventory.adjust';

    // Reporting
    case ReportStockView = 'report.stock.view';
    case ReportRequestView = 'report.request.view';
    case ReportPurchasingView = 'report.purchasing.view';
    case ReportNeedToBuyView = 'report.need_to_buy.view';
    case ReportExport = 'report.export';

    // Administrasi
    case UserManage = 'user.manage';
    case RoleManage = 'role.manage';
    case AuditView = 'audit.view';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /**
     * Permission per role.
     *
     * Setiap user WAJIB memegang role `requester` sebagai dasar — blueprint
     * menyebutnya "role dasar seluruh pegawai". Role fungsional karena itu hanya
     * memuat permission TAMBAHAN, tidak mengulang yang sudah ada di requester.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $requester = [
            self::RequestCreate,
            self::RequestViewOwn,
            self::RequestUpdate,
            self::RequestSubmit,
            self::RequestCancel,
            self::ItemView,
        ];

        $supervisor = [
            self::RequestViewSubordinate,
            self::RequestApproveL1,
            self::ReportRequestView,
            self::ReportExport,
        ];

        $picStationery = [
            self::RequestViewAll,
            self::RequestApproveL2,
            self::ItemCreate,
            self::ItemUpdate,
            self::CategoryManage,
            self::UomManage,
            self::PurchaseView,
            self::PurchaseVerify,
            self::InventoryView,
            self::InventoryAdjust,
            self::ReportStockView,
            self::ReportRequestView,
            self::ReportPurchasingView,
            self::ReportNeedToBuyView,
            self::ReportExport,
        ];

        $sgaManager = [
            self::RequestViewAll,
            self::RequestApproveL3,
            self::PurchaseView,
            self::InventoryView,
            self::ReportStockView,
            self::ReportRequestView,
            self::ReportPurchasingView,
            self::ReportNeedToBuyView,
            self::ReportExport,
        ];

        $warehouseOfficer = [
            self::RequestViewAll,
            self::RequestHandover,
            self::PurchaseCreate,
            self::PurchaseView,
            self::PurchaseUpdate,
            self::InventoryView,
            self::ReportStockView,
            self::ReportPurchasingView,
            self::ReportNeedToBuyView,
            self::ReportExport,
        ];

        $toValues = static fn (array $perms): array => array_map(
            static fn (self $p): string => $p->value,
            $perms,
        );

        return [
            Role::Requester->value => $toValues($requester),
            Role::Supervisor->value => $toValues($supervisor),
            Role::PicStationery->value => $toValues($picStationery),
            Role::SgaManager->value => $toValues($sgaManager),
            Role::WarehouseOfficer->value => $toValues($warehouseOfficer),
            // Administrator memegang seluruh permission.
            Role::Administrator->value => self::values(),
        ];
    }
}

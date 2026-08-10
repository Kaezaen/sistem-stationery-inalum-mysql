<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,

    /*
    |--------------------------------------------------------------------------
    | Modul Aplikasi
    |--------------------------------------------------------------------------
    |
    | Setiap modul memuat route dan Policy-nya sendiri lewat ModuleServiceProvider.
    | Urutan mengikuti arah ketergantungan pada §2.1 Architecture Blueprint:
    | fondasi lebih dulu, layanan lintas modul, lalu modul bisnis.
    |
    | Menambah modul baru = menambah satu baris di sini. Tidak ada berkas
    | terpusat lain yang perlu disentuh (ADR-02).
    |
    */

    // Fondasi
    App\Modules\Identity\IdentityServiceProvider::class,
    App\Modules\Catalog\CatalogServiceProvider::class,

    // Layanan lintas modul
    App\Modules\Approval\ApprovalServiceProvider::class,
    App\Modules\Inventory\InventoryServiceProvider::class,
    App\Modules\Notification\NotificationServiceProvider::class,
    App\Modules\Audit\AuditServiceProvider::class,

    // Modul bisnis
    App\Modules\Requisition\RequisitionServiceProvider::class,
    App\Modules\Purchasing\PurchasingServiceProvider::class,
    App\Modules\Fulfillment\FulfillmentServiceProvider::class,
    App\Modules\Reporting\ReportingServiceProvider::class,
];

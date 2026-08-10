<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\StockStatus;

/*
| Aturan status stok di-reverse-engineer dari contoh angka pada wireframe 3.11.2.
| Test ini mengunci interpretasi tersebut: bila kelak pemilik proses bisnis
| mengoreksinya, kegagalan di sini yang pertama memberi tahu.
*/

it('mengikuti contoh angka pada wireframe', function (int $stock, int $min, int $max, StockStatus $expected): void {
    expect(StockStatus::evaluate($stock, $min, $max))->toBe($expected);
})->with([
    'BALL LINER — stok di atas max' => [15, 5, 10, StockStatus::OverStock],
    'PERMANENT MARKER — stok di bawah min' => [3, 5, 10, StockStatus::UnderStock],
    'ERASER — stok di antara min dan max' => [7, 5, 10, StockStatus::StockOnHand],
]);

it('memperlakukan batas sebagai inklusif', function (): void {
    // Tepat di min dan tepat di max BUKAN kondisi bermasalah — hanya yang
    // melampaui batas yang berstatus khusus.
    expect(StockStatus::evaluate(5, 5, 10))->toBe(StockStatus::StockOnHand)
        ->and(StockStatus::evaluate(10, 5, 10))->toBe(StockStatus::StockOnHand);
});

it('menandai stok nol sebagai under stock bila min di atas nol', function (): void {
    expect(StockStatus::evaluate(0, 5, 10))->toBe(StockStatus::UnderStock);
});

it('menandai hanya under stock yang perlu dibeli', function (): void {
    expect(StockStatus::UnderStock->needsRestock())->toBeTrue()
        ->and(StockStatus::StockOnHand->needsRestock())->toBeFalse()
        ->and(StockStatus::OverStock->needsRestock())->toBeFalse();
});

it('menangani min dan max bernilai nol', function (): void {
    // Item baru sebelum batas ditetapkan: stok berapa pun di atas 0 dianggap
    // over stock. Bukan bug — ini konsekuensi wajar dari batas yang belum diisi.
    expect(StockStatus::evaluate(0, 0, 0))->toBe(StockStatus::StockOnHand)
        ->and(StockStatus::evaluate(1, 0, 0))->toBe(StockStatus::OverStock);
});

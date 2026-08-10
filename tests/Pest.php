<?php

declare(strict_types=1);

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Konfigurasi Test
|--------------------------------------------------------------------------
|
| Uji Architecture memakai TestCase juga karena sebagian aturan membaca
| base_path() dan bootstrap/providers.php — keduanya butuh aplikasi hidup.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Architecture');

/*
| RefreshDatabase sengaja TIDAK dipasang global.
|
| Dipasang per berkas dengan `uses(RefreshDatabase::class);` agar terlihat jelas
| test mana yang benar-benar menyentuh database. Test yang tidak butuh DB tetap
| dapat berjalan sebelum PostgreSQL tersedia, dan suite secara keseluruhan tidak
| membayar biaya migrasi untuk test yang tidak memerlukannya.
|
| Sejak Fase 1, hampir seluruh Feature test akan memakainya.
*/

<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('menampilkan halaman masuk', function (): void {
    get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

it('mengarahkan tamu ke halaman masuk saat membuka dashboard', function (): void {
    get('/')->assertRedirect('/login');
});

it('menerima login dengan username', function (): void {
    $user = User::factory()->create(['username' => 'mawan']);

    post('/login', ['login' => 'mawan', 'password' => 'password'])
        ->assertRedirect('/');

    expect(auth()->id())->toBe($user->id);
});

it('menerima login dengan email', function (): void {
    $user = User::factory()->create(['email' => 'mawan@inalum.id']);

    post('/login', ['login' => 'mawan@inalum.id', 'password' => 'password'])
        ->assertRedirect('/');

    expect(auth()->id())->toBe($user->id);
});

it('menolak kata sandi yang salah', function (): void {
    User::factory()->create(['username' => 'mawan']);

    post('/login', ['login' => 'mawan', 'password' => 'salah'])
        ->assertSessionHasErrors('login');

    assertGuest();
});

it('menolak user non-aktif meski kata sandinya benar', function (): void {
    // User non-aktif harus ditolak SEBELUM sesi terbentuk — pegawai yang sudah
    // keluar tidak boleh bisa masuk hanya karena akunnya belum dihapus.
    User::factory()->inactive()->create(['username' => 'mantan']);

    post('/login', ['login' => 'mantan', 'password' => 'password'])
        ->assertSessionHasErrors('login');

    assertGuest();
});

it('mencatat waktu login terakhir', function (): void {
    $user = User::factory()->create(['username' => 'mawan', 'last_login_at' => null]);

    post('/login', ['login' => 'mawan', 'password' => 'password']);

    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('membatasi percobaan masuk yang berulang', function (): void {
    User::factory()->create(['username' => 'mawan']);

    foreach (range(1, 5) as $ignored) {
        post('/login', ['login' => 'mawan', 'password' => 'salah']);
    }

    post('/login', ['login' => 'mawan', 'password' => 'salah'])
        ->assertSessionHasErrors('login');

    expect(session('errors')->first('login'))->toContain('Terlalu banyak percobaan');
});

it('mengakhiri sesi saat logout', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    assertGuest();
});

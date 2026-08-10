<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $employee_id
 * @property string $username
 * @property string $name
 * @property string $email
 * @property int $department_id
 * @property string|null $position
 * @property int|null $manager_id
 * @property bool $is_active
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'username',
        'name',
        'email',
        'password',
        'department_id',
        'position',
        'manager_id',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Menunjuk factory secara eksplisit.
     *
     * Resolusi otomatis Laravel mengasumsikan model berada di App\Models dan akan
     * mencari Database\Factories\Modules\Identity\Models\UserFactory. Karena
     * arsitektur ini menempatkan model di dalam modul, penunjukan harus manual.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi
    |--------------------------------------------------------------------------
    */

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Atasan langsung — penentu approver Level 1.
     *
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /** @return HasMany<User, $this> */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query scope
    |--------------------------------------------------------------------------
    */

    /** @param Builder<User> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Perilaku domain
    |--------------------------------------------------------------------------
    */

    /**
     * Apakah user ini atasan LANGSUNG dari $other.
     *
     * Dipakai RequestPolicy::approveL1. Sengaja hanya satu tingkat: blueprint
     * mengarahkan approval L1 ke atasan langsung, bukan ke seluruh rantai ke atas.
     */
    public function isDirectManagerOf(self $other): bool
    {
        return $other->manager_id !== null && $other->manager_id === $this->id;
    }

    /**
     * Rantai atasan dari yang terdekat ke paling atas.
     *
     * Diberi batas kedalaman sebagai pengaman: bila data organisasi terlanjur
     * mengandung siklus, method ini tetap berhenti alih-alih berulang selamanya.
     *
     * @return list<User>
     */
    public function managerChain(int $maxDepth = 10): array
    {
        $chain = [];
        $seen = [$this->id => true];
        $current = $this->manager;

        while ($current !== null && count($chain) < $maxDepth) {
            if (isset($seen[$current->id])) {
                break;
            }

            $seen[$current->id] = true;
            $chain[] = $current;
            $current = $current->manager;
        }

        return $chain;
    }
}

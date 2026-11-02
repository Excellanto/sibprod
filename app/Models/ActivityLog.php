<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_name',
        'module',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subject model that was affected.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by module.
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope to filter by action.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        return $query;
    }

    /**
     * Scope to filter by modules the user has access to.
     */
    public function scopeAccessibleModules($query, User $user)
    {
        // Super admin sees everything
        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        // Get all permissions the user has
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        
        // Extract module names from permissions
        // Permissions are typically named like: "users.view", "roles.create", etc.
        $accessibleModules = [];
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission);
            if (count($parts) >= 2) {
                $accessibleModules[] = $parts[0];
            }
        }

        // Remove duplicates
        $accessibleModules = array_unique($accessibleModules);

        // If user has no permissions, return empty result
        if (empty($accessibleModules)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('module', $accessibleModules);
    }

    /**
     * Get old values from properties.
     */
    public function getOldValuesAttribute()
    {
        return $this->properties['old'] ?? null;
    }

    /**
     * Get new values from properties.
     */
    public function getNewValuesAttribute()
    {
        return $this->properties['new'] ?? null;
    }

    /**
     * Get changes from properties.
     */
    public function getChangesAttribute()
    {
        return $this->properties['changes'] ?? null;
    }
}

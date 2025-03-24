<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'domain_id',
        'action',
        'description',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Get the user that performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the domain associated with this log entry.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the related model for this log entry if available.
     */
    public function loggable()
    {
        if ($this->model_type && $this->model_id) {
            return $this->morphTo('loggable', 'model_type', 'model_id');
        }
        return null;
    }

    /**
     * Scope a query to only include logs for a specific domain.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $domainId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDomain($query, $domainId)
    {
        return $query->where('domain_id', $domainId);
    }

    /**
     * Scope a query to only include logs for a specific action type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $action
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include logs for a specific user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include logs for a specific model type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $modelType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForModel($query, $modelType)
    {
        return $query->where('model_type', $modelType);
    }
}

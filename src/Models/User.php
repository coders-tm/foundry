<?php

namespace Foundry\Models;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Database\Factories\UserFactory;
use Foundry\Enum\AppRag;
use Foundry\Enum\AppStatus;
use Foundry\Exceptions\ImportFailedException;
use Foundry\Exceptions\ImportSkippedException;
use Foundry\Foundry;
use Foundry\Models\Subscription\Plan;
use Foundry\Traits\Addressable;
use Foundry\Traits\Billable;
use Foundry\Traits\Core;
use Foundry\Traits\Fileable;
use Foundry\Traits\HasWallet;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\ISO3166\ISO3166;

class User extends Authenticatable implements MustVerifyEmail
{
    use Addressable, Core, Fileable, Notifiable;
    use Billable, HasWallet;

    protected $guard = 'user';

    protected $fillable = [
        'email',
        'first_name',
        'gender',
        'last_name',
        'note',
        'password',
        'phone_number',
        'source',
        'last_login_at',
        'settings',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'rag' => AppRag::class,
        'status' => AppStatus::class,
        'is_active' => 'boolean',
        'is_free_forever' => 'boolean',
        'settings' => 'array',
    ];

    protected $appends = [
        'name',
        'member_since',
    ];

    protected $with = [
        'avatar',
    ];

    public function getNameAttribute()
    {
        return $this->attributes['name'] ?? trim("{$this->first_name} {$this->last_name}") ?: null;
    }

    /**
     * Route notifications for the mail channel.
     *
     * @return array<string, string>|string
     */
    public function routeNotificationForMail($notification): array|string
    {
        return [$this->email => $this->name];
    }

    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens()->pluck('token')->toArray();
    }

    public function routeNotificationForTwilio()
    {
        return $this->phone_number;
    }

    public function getMemberSinceAttribute()
    {
        return $this->created_at?->format('Y');
    }

    /**
     * Get currency from settings
     */
    public function getCurrencyAttribute()
    {
        $settings = $this->attributes['settings'] ?? null;

        // Handle both JSON string and array
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?? [];
        } elseif (! is_array($settings)) {
            $settings = [];
        }

        return $settings['currency'] ?? null;
    }

    /**
     * Set currency in settings
     */
    public function setCurrencyAttribute($value)
    {
        $settings = $this->attributes['settings'] ?? null;

        // Handle both JSON string and array
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?? [];
        } elseif (! is_array($settings)) {
            $settings = [];
        }

        if ($value === null) {
            unset($settings['currency']);
        } else {
            $settings['currency'] = $value;
        }

        // Store as JSON string since the column is JSON
        $this->attributes['settings'] = json_encode($settings);
    }

    public function notes()
    {
        return $this->morphMany(Log::class, 'logable')
            ->whereNotIn('type', ['login'])
            ->orderBy('created_at', 'desc')
            ->withOnly(['admin']);
    }

    public function lastUpdate()
    {
        return $this->morphOne(Log::class, 'logable')
            ->where('type', 'notes')
            ->orderBy('created_at', 'desc');
    }

    /**
     * @deprecated Use updateExpiresAt() instead.
     */
    public function updateCancelsAt($dateAt)
    {
        return $this->updateExpiresAt($dateAt);
    }

    public function updateExpiresAt($expiresAt)
    {
        if (! $expiresAt) {
            throw new \InvalidArgumentException('Expires at cannot be empty.');
        }

        if ($this->subscription()) {
            $this->subscription()->update([
                'expires_at' => $expiresAt,
            ]);
        }

        return $this;
    }

    /**
     * Get all of the support tickets for the User
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(Foundry::$supportTicketModel, 'email', 'email');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Eager load unread support tickets counts on the User.
     */
    public function loadUnreadSupportTickets()
    {
        return $this->loadCount([
            'supportTickets as unread_support_tickets' => function (Builder $query) {
                $query->onlyActive();
            },
        ]);
    }

    public function lastLogin(): MorphOne
    {
        return $this->morphOne(Log::class, 'logable')
            ->where('type', 'login')
            ->orderBy('created_at', 'desc');
    }

    public function createdBy(): MorphOne
    {
        return $this->morphOne(Log::class, 'logable')->whereType('created');
    }

    public function isActive()
    {
        return $this->is_active;
    }

    public function requestAccountDeletion()
    {
        return $this->morphOne(Log::class, 'logable')
            ->where('type', 'request-account-deletion')
            ->where('created_at', '>', now()->subDays(7))
            ->whereColumn('created_at', 'updated_at')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to only include onlyActive
     */
    public function scopeOnlyActive($query): Builder
    {
        return $query->where([
            'status' => AppStatus::ACTIVE,
        ]);
    }

    /**
     * Scope a query to only include onlySupportTicket
     */
    public function scopeOnlySupportTicket($query): Builder
    {
        return $query->where('status', '<>', AppStatus::ACTIVE);
    }

    /**
     * Scope a query to only include onlyMember
     */
    public function scopeOnlyMember($query): Builder
    {
        return $query->where('status', AppStatus::ACTIVE);
    }

    /**
     * Scope a query to only include onlyCancelled
     */
    public function scopeOnlyCancelled($query): Builder
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->canceled();
        });
    }

    /**
     * Scope a query to only include onlyMonthlyPlan
     */
    public function scopeOnlyMonthlyPlan($query): Builder
    {
        return $query->onlyPlan('month');
    }

    /**
     * Scope a query to only include onlyYearlyPlan
     */
    public function scopeOnlyYearlyPlan($query): Builder
    {
        return $query->onlyPlan('year');
    }

    /**
     * Scope a query to only include onlyPlan
     *
     * @param  string  $type  year|month|day
     */
    public function scopeOnlyPlan($query, string $type = 'month'): Builder
    {
        return $query->whereHas('subscriptions', function ($q) use ($type) {
            $q->active()
                ->whereNull('canceled_at')
                ->whereHas('plan', function ($q) use ($type) {
                    $q->whereInterval($type)
                        ->where('price', '<>', 0);
                });
        });
    }

    /**
     * Scope a query to only include onlyRolling
     */
    public function scopeOnlyRolling($query): Builder
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->active()->whereNull('canceled_at');
        });
    }

    /**
     * Scope a query to only include onlyEnds
     */
    public function scopeOnlyEnds($query): Builder
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->active()->whereNotNull('canceled_at');
        });
    }

    /**
     * Scope a query to only include onlyFree
     */
    public function scopeOnlyFree($query): Builder
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->active()->whereHas('plan', function ($q) {
                $q->wherePrice(0);
            });
        });
    }

    /**
     * Scope a query to only include whereTyped
     */
    public function scopeWhereTyped($query, ?string $type = null): Builder
    {
        switch ($type) {
            case 'rolling':
                $query->onlyRolling();
                break;

            case 'ends':
            case 'end_date':
                $query->onlyEnds();
                break;

            case 'month':
            case 'year':
                $query->onlyPlan($type);
                break;

            case 'free':
                $query->onlyFree();
                break;
        }

        return $query;
    }

    /**
     * Scope a query to only include sortBy
     */
    public function scopeSortBy($query, $column = 'CREATED_AT_ASC', $direction = 'asc'): Builder
    {
        switch ($column) {
            case 'last_login':
                $query->orderBy('last_login_at', $direction ?? 'asc');
                break;

            case 'last_update':
                $query->orderByRaw('(SELECT MAX(created_at) FROM logs WHERE logs.logable_id = users.id AND logs.logable_type = ? AND logs.type = ?) '.($direction ?? 'asc'), [$this->getMorphClass(), 'notes']);
                break;

            case 'created_by':
                $query->orderByRaw(
                    'CASE
                        WHEN (SELECT admin_id FROM logs WHERE logs.logable_id = users.id AND logs.logable_type = ? AND logs.type = ? ORDER BY created_at DESC LIMIT 1) IS NOT NULL
                        THEN (SELECT first_name FROM admins WHERE admins.id = (SELECT admin_id FROM logs WHERE logs.logable_id = users.id AND logs.logable_type = ? AND logs.type = ? ORDER BY created_at DESC LIMIT 1))
                        ELSE JSON_EXTRACT((SELECT options FROM logs WHERE logs.logable_id = users.id AND logs.logable_type = ? AND logs.type = ? ORDER BY created_at DESC LIMIT 1), "$.ref")
                    END '.($direction ?? 'asc'),
                    [$this->getMorphClass(), 'created', $this->getMorphClass(), 'created', $this->getMorphClass(), 'created']
                );
                break;

            case 'price':
                $query->orderByRaw('(
                    SELECT label
                    FROM (
                        SELECT label
                        FROM plans
                        WHERE id = (
                            SELECT plan_id
                            FROM subscriptions
                            WHERE user_id = users.id
                            ORDER BY created_at DESC
                            LIMIT 1
                        )
                        LIMIT 1
                    ) AS subquery
                ) '.($direction ?? 'asc'));
                break;

            case 'name':
                $query->orderBy(DB::raw('CONCAT(`first_name`, `last_name`)'), $direction ?? 'asc');
                break;

            default:
                $allowedColumns = ['created_at', 'last_login_at', 'email', 'first_name', 'last_name', 'updated_at'];
                $column = in_array($column, $allowedColumns, true) ? $column : 'created_at';
                $direction = in_array(strtolower((string) $direction), ['asc', 'desc'], true) ? $direction : 'asc';
                $query->orderBy($column, $direction);
                break;
        }

        return $query;
    }

    public function scopeWhereName($query, $filter)
    {
        return $query->where(DB::raw('CONCAT(`first_name`,`last_name`)'), 'like', "%{$filter}%");
    }

    /**
     * Scope a query to only include withUnreadSupportTickets
     */
    public function scopeWithUnreadSupportTickets($query): Builder
    {
        return $query->withCount([
            'supportTickets as unread_support_tickets' => function (Builder $query) {
                $query->onlyActive();
            },
        ]);
    }

    /**
     * Scope a query to only include whereDateColumn
     */
    public function scopeWhereDateColumn($query, $date = [], $column = 'created_at'): Builder
    {
        return $query->whereHas('subscriptions', function ($q) use ($date, $column) {
            if (isset($date['year'])) {
                $q->whereYear($column, $date['year']);
            }
            if (isset($date['month'])) {
                $q->whereMonth($column, $date['month']);
            }
            if (isset($date['day'])) {
                $q->whereDay($column, $date['day']);
            }
        });
    }

    public function toLoginResponse()
    {
        return $this->loadUnreadSupportTickets()->toArray() + [
            'subscription' => $this->subscription()?->toResponse(['plan', 'invoice', 'usages']),
        ];
    }

    public function getShortCodes(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
        ];
    }

    public static function getMappedAttributes(): array
    {
        return [
            'First Name' => 'first_name',
            'Surname' => 'last_name',
            'Gender' => 'gender',
            'Email Address' => 'email',
            'Phone Number' => 'phone_number',
            'Status' => 'status',
            'Deactivates At' => 'deactivates_at',
            'Password' => 'password',
            'Created At' => 'created_at',
            'Plan' => 'plan',
            'Trial Ends At' => 'trial_ends_at',
            'Address Line1' => 'line1',
            'Address Line2' => 'line2',
            'Country' => 'country',
            'State' => 'state',
            'State Code' => 'state_code',
            'City' => 'city',
            'Postcode/Zip' => 'postal_code',
            'Note' => 'note',
        ];
    }

    public static function createFromCsv(array $attributes = [], array $options = [])
    {
        $replaceByEmail = isset($options['email_overwrite']) && $options['email_overwrite'];
        $user = static::where('email', $attributes['email'])->withTrashed()->first();

        if (! $replaceByEmail && $user) {
            throw new ImportFailedException;
        } elseif ($user && ($user->wasRecentlyUpdated || $user->wasRecentlyCreated)) {
            throw new ImportSkippedException;
        }

        if (isset($attributes['password'])) {
            $attributes['password'] = bcrypt($attributes['password']);
        }

        if (isset($attributes['country'])) {
            try {
                $country = (new ISO3166)->name($attributes['country']);
                $attributes['country_code'] = $country['alpha2'];
            } catch (\Throwable $e) {
                $attributes['country_code'] = null;
            }
        }

        $user = static::firstOrNew([
            'email' => $attributes['email'],
        ], $attributes);

        if (isset($attributes['created_at']) && ! empty($attributes['created_at'])) {
            $user->created_at = $attributes['created_at'];
        }

        $user->deleted_at = null;

        $user->save();

        $user->updateOrCreateAddress($attributes);

        // Handle subscription creation with plan and trial_ends_at
        if (isset($attributes['plan'])) {
            $planId = $attributes['plan'];
            // Find plan by ID or name
            $plan = Plan::find($planId);
            if (! $plan && is_string($planId)) {
                $plan = Plan::where('name', $planId)->first();
            }

            if (! $plan) {
                throw new ImportFailedException("Plan with ID/name {$attributes['plan']} not found.");
            }

            $status = Str::lower($attributes['status'] ?? 'active') === 'active' ? SubscriptionStatus::ACTIVE : SubscriptionStatus::INCOMPLETE;
            $expiresAt = $attributes['deactivates_at'] ?? null;

            try {
                // Check if user already has a 'default' subscription
                $existing = $user->subscription('default');
                if ($existing) {
                    // Update existing subscription
                    $existing->plan_id = $plan->getKey();
                    $existing->status = $status;
                    if (! empty($attributes['trial_ends_at'])) {
                        $existing->trialUntil($attributes['trial_ends_at']);
                    }
                    if (! empty($expiresAt)) {
                        $existing->expires_at = $expiresAt;
                    }
                    $existing->saveWithoutInvoice();
                } else {
                    // Create new subscription
                    $subscription = $user->newSubscription('default', $plan);
                    $subscription->status = $status;
                    if (! empty($attributes['trial_ends_at'])) {
                        $subscription->trialUntil($attributes['trial_ends_at']);
                    }
                    if (! empty($expiresAt)) {
                        $subscription->expires_at = $expiresAt;
                    }
                    $subscription->saveWithoutInvoice();
                }
            } catch (\Throwable $e) {
                throw new ImportFailedException('Failed to create or update subscription for imported user: '.$e->getMessage());
            }
        }
    }

    /**
     * Get the orders for the user.
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * Get the guard attribute.
     *
     * @return string
     */
    public function getGuardAttribute()
    {
        return $this->guard;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return UserFactory::new();
    }

    public function addDeviceToken(string $deviceToken)
    {
        if (! $deviceToken) {
            throw new \InvalidArgumentException('Device token cannot be empty.');
        }

        return $this->deviceTokens()->updateOrCreate([
            'token' => $deviceToken,
        ]);
    }

    protected static function booted()
    {
        static::updated(function ($model) {
            Foundry::$supportTicketModel::where('email', $model->getOriginal('email'))->update([
                'email' => $model->email,
            ]);
        });
    }
}

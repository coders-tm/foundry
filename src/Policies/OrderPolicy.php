<?php

namespace Foundry\Policies;

use Foundry\Models\Admin;
use Foundry\Models\Order;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization policy for subscription Orders.
 *
 * Users may view and cancel their own orders.
 * Admins may view all orders and manage statuses.
 */
class OrderPolicy
{
    /**
     * Super-admins bypass all checks.
     */
    public function before(Model $user, string $ability): ?bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return null;
    }

    /**
     * Users can list their own orders; admins can list all.
     */
    public function viewAny(Model $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->canAny(['orders:read', 'orders:write', 'orders:editor']);
        }

        return true;
    }

    /**
     * Users can view their own order; admins can view any.
     */
    public function view(Model $user, Order $order): bool
    {
        // Admin guard: always allowed
        if ($this->isAdmin($user)) {
            return $user->canAny(['orders:read', 'orders:write', 'orders:editor']);
        }

        return (int) $order->customer_id === (int) $user->id;
    }

    /**
     * Only admins can create orders directly (users subscribe via Subscription flow).
     */
    public function create(Model $user): bool
    {
        return $this->isAdmin($user) && $user->canAny(['orders:write', 'orders:editor']);
    }

    /**
     * Only admins can update orders.
     */
    public function update(Model $user, Order $order): bool
    {
        return $this->isAdmin($user) && $user->canAny(['orders:write', 'orders:editor']);
    }

    /**
     * Users can cancel unpaid orders; admins can cancel any.
     */
    public function cancel(Model $user, Order $order): bool
    {
        if ($this->isAdmin($user)) {
            return $user->canAny(['orders:write', 'orders:editor']);
        }

        return (int) $order->customer_id === (int) $user->id && ! $order->is_paid;
    }

    /**
     * Only admins can manage (update status of) orders.
     */
    public function manage(Model $user, Order $order): bool
    {
        return $this->isAdmin($user) && $user->canAny(['orders:write', 'orders:editor']);
    }

    /**
     * Only admins can delete orders.
     */
    public function delete(Model $user, Order $order): bool
    {
        return $this->isAdmin($user) && $user->can('orders:write');
    }

    private function isAdmin(Model $user): bool
    {
        return $user instanceof Admin && in_array($user->guard, ['admin']);
    }
}

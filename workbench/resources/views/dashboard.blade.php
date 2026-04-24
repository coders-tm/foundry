@extends('layout', ['title' => 'User Dashboard'])

@section('content')
<div class="dashboard-container">
    <div class="nav">
        <div>
            <h1 style="display:inline; margin-right: 1rem;">User Dashboard</h1>
            <span class="badge badge-user">Standard User</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="width: auto; padding: 0.5rem 1rem; background-color: var(--border);">Logout</button>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Active Subscriptions</div>
            <div class="stat-value">3</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Recent Orders</div>
            <div class="stat-value">12</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Available Balance</div>
            <div class="stat-value">$142.50</div>
        </div>
    </div>
</div>
@endsection

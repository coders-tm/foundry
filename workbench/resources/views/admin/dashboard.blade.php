@extends('layout', ['title' => 'Admin Dashboard'])

@section('content')
<div class="dashboard-container">
    <div class="nav">
        <div>
            <h1 style="display:inline; margin-right: 1rem; background: linear-gradient(to right, #f87171, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Admin Control Center</h1>
            <span class="badge badge-admin">Super Admin</span>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" style="width: auto; padding: 0.5rem 1rem; background-color: #7f1d1d;">Secure Logout</button>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">$124,592</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">New Users (24h)</div>
            <div class="stat-value">84</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">System Health</div>
            <div class="stat-value" style="color: #4ade80;">Optimal</div>
        </div>
    </div>
</div>
@endsection

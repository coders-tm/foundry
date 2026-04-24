@extends('layout', ['title' => 'Admin Login'])

@section('content')
<div class="card">
    <h1>Admin Control</h1>
    <p class="subtitle">Secure access for foundry administrators</p>

    <form method="POST" action="{{ route('admin.login') }}">
        @csrf
        <div class="form-group">
            <label for="email">Admin Email</label>
            <input type="email" id="email" name="email" value="admin@example.com" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" value="password" required>
        </div>

        <button type="submit" style="background-color: #dc2626;">Authenticate Admin</button>
    </form>

    <div class="footer-link">
        Not an admin? <a href="{{ route('login') }}">User Login</a>
    </div>
</div>
@endsection

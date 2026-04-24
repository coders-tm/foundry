@extends('layout', ['title' => 'User Login'])

@section('content')
<div class="card">
    <h1>Welcome Back</h1>
    <p class="subtitle">Enter your credentials to access your user account</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="user@example.com" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" value="password" required>
        </div>

        <button type="submit">Sign In</button>
    </form>

    <div class="footer-link">
        Are you an admin? <a href="{{ route('admin.login') }}">Admin Login</a>
    </div>
</div>
@endsection

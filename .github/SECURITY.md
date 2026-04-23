# Security Policy

## Reporting Security Vulnerabilities

**Please do not disclose security vulnerabilities through public GitHub issues.** This helps prevent the exploitation of potential security issues before they have been identified and resolved.

### How to Report

If you discover a security vulnerability in `coderstm/foundry`, please email us at **security@foundry.com** with the following information:

- **Description** of the vulnerability
- **Steps to reproduce** the issue (if applicable)
- **Potential impact** and severity assessment
- **Affected versions** of the package
- **Your contact information** (for follow-up if needed)

### Response Timeline

We take security seriously and will respond to all reports within **48 hours** of receipt. Here's our disclosure process:

1. **Initial Response** (within 48 hours) — Acknowledgment of the vulnerability report and initial assessment
2. **Investigation** (1-7 days) — We will investigate the issue and determine its severity
3. **Fix Development** (3-30 days depending on severity) — We develop and test a fix
4. **Security Release** — A patch release is issued containing the fix
5. **Public Disclosure** — Once a fix is available, we will announce the vulnerability and credit the reporter

---

## Security Considerations for Package Users

### Dependency Management

`coderstm/foundry` integrates with multiple third-party payment processors and external services. Keep your dependencies updated:

```bash
composer update
```

### Payment Gateway Credentials

**Never hardcode credentials in your application code.** Always use environment variables:

```php
// ✅ CORRECT
$apiKey = config('services.stripe.secret');

// ❌ WRONG
$apiKey = 'sk_live_...' // Directly in code
```

Store secrets in `.env` file (gitignored):

```bash
STRIPE_SECRET=sk_live_...
PAYPAL_CLIENT_ID=...
```

### Database Security

- Use Laravel migrations to manage schema changes
- Enable query logging only in development environments
- Use parameter binding to prevent SQL injection (already enforced in models)

### Authentication & Authorization

- Use the `HasPermission` trait for admin access control
- Always validate user permissions before sensitive operations
- Use `auth` and `is_admin()` helpers for multi-guard contexts
- Implement CSRF protection on all state-changing routes

### Webhook Security

When receiving webhooks from payment processors:

1. **Verify webhook signatures** — All processor webhooks must be verified
2. **Use HTTPS only** — Webhook endpoints must be HTTPS
3. **Validate source** — Check webhook origin before processing
4. **Idempotent operations** — Design webhook handlers to be idempotent (safe to call multiple times)

Example verification (Stripe):

```php
$signature = $request->header('Stripe-Signature');
$payload = $request->getContent();

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $signature,
        config('services.stripe.webhook_secret')
    );
} catch (\UnexpectedValueException $e) {
    return response('Webhook signature verification failed', 403);
}
```

### PCI Compliance

- **Never store raw card data** — Use saved payment methods or tokens from processors
- **Use HTTPS** — All payment data must be encrypted in transit
- **Limited access** — Restrict access to payment-related code
- **Regular audits** — Periodically review payment handling code

### SQL Injection Prevention

The package uses Laravel's query builder and Eloquent ORM which provide automatic parameterization:

```php
// ✅ SAFE - Uses parameterized queries
$user = User::where('email', $email)->first();

// ❌ UNSAFE - Never do this
$user = User::whereRaw("email = '$email'")->first();
```

### Cross-Site Scripting (XSS)

Always escape user input in Blade templates:

```blade
{{-- ✅ CORRECT - Auto-escaped --}}
<p>{{ $user->name }}</p>

{{-- ❌ WRONG - Unescaped HTML --}}
<p>{!! $user->description !!}</p>

{{-- ✅ CORRECT - Use when HTML is trusted --}}
<p>{!! nl2br(e($user->message)) !!}</p>
```

### Rate Limiting

Implement rate limiting on sensitive endpoints:

```php
Route::post('/api/payments', function () {
    // ...
})->middleware('throttle:60,1'); // 60 requests per minute
```

---

## Supported Versions

| Version | PHP | Laravel | Security Updates Until |
|---------|-----|---------|------------------------|
| 2.x     | 8.2+ | 11.0+ | April 2026 |
| 1.x     | 8.1+ | 10.0+ | October 2024 |

Only the latest version receives security updates. We recommend upgrading to the latest version promptly.

---

## Security Best Practices

### 1. Keep Laravel Core Updated

Subscribe to security announcements via GitHub Releases:

```bash
# Watch the repository for releases
# https://github.com/coders-tm/foundry/releases
```

### 2. Use Composer Security Audit

Regularly check dependencies for known vulnerabilities:

```bash
composer audit
```

### 3. Enable Query Logging in Development Only

```php
// config/database.php
'log_queries' => env('LOG_QUERIES', false),
```

### 4. Use Sanctum for API Authentication

For API endpoints, use Laravel Sanctum:

```php
// app/Models/User.php
use Laravel\Sanctum\HasApiTokens;

class User extends Model
{
    use HasApiTokens;
}
```

### 5. Rotate Payment Processor Keys

Periodically rotate API keys and webhooks signatures from payment processors:

- Stripe: https://dashboard.stripe.com/apikeys
- PayPal: https://developer.paypal.com/dashboard
- Other processors: Check their documentation

### 6. Monitor Failed Payments & Webhooks

Track failed payments and webhook processing errors:

```php
// Log failed payment attempts
\Log::error('Payment failed', [
    'order_id' => $order->id,
    'processor' => 'stripe',
    'reason' => $result->error,
]);

// Implement alerting for webhook failures
```

### 7. Implement Request Validation

Always validate incoming requests:

```php
// Use form requests
$validated = $request->validate([
    'amount' => 'required|numeric|min:0.01',
    'currency' => 'required|string|max:3',
    'email' => 'required|email',
]);
```

### 8. Use Content Security Policy (CSP)

Implement CSP headers to prevent XSS attacks:

```php
// config/hsts.php or middleware
header("Content-Security-Policy: default-src 'self'; script-src 'self' cdn.example.com;");
```

---

## Third-Party Security Audits

We maintain a record of third-party security audits and penetration tests:

- Last audit: [Date TBD]
- Next scheduled audit: [Date TBD]

To request a copy of recent audit reports, please contact the security team.

---

## Security Acknowledgments

We credit security researchers who responsibly disclose vulnerabilities. If you'd like to be acknowledged in our security hall of fame, please let us know when reporting the vulnerability.

Thank you to the following researchers for helping keep Laravel Core secure:

- [Contributors will be listed here upon responsible disclosure]

---

## Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Documentation](https://laravel.com/docs/security)
- [Laravel Fortify](https://laravel.com/docs/fortify) - Authentication scaffolding
- [Laravel Sanctum](https://laravel.com/docs/sanctum) - API token authentication
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)

---

## Contact

For security-related inquiries:

- **Email**: security@foundry.com
- **GitHub**: [Report via Security Advisory](https://github.com/coders-tm/foundry/security/advisories)
- **Website**: [https://foundry.com](https://foundry.com)

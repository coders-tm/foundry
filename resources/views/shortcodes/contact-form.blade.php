<form id="{{ $id }}" action="/contact" class="request_qoute_form wrap-form clearfix {{ $class }}"
    method="post" novalidate="novalidate">
    @csrf
    <input type="hidden" name="recaptcha_token" id="recaptcha_token">
    <div class="row ct-boxes-spacing-20px">
        <div class="col-lg-12">
            @if (session('success'))
                <div id="form-success" class="alert alert-success" role="alert">
                    {{ session('success') }}
                </div>
            @endif
            @error('recaptcha_token')
                <div class="alert alert-danger" role="alert">
                    {{ $message }}
                </div>
            @enderror
        </div>
        <div class="col-md-6">
            <label>
                <span class="text-input">
                    <input class="@error('name') is-invalid @enderror" name="name" type="text"
                        value="{{ old('name') }}" placeholder="Name" required="required">
                    @error('name')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </span>
            </label>
        </div>
        <div class="col-md-6">
            <label>
                <span class="text-input">
                    <input class="@error('phone') is-invalid @enderror" name="phone" type="text"
                        value="{{ old('phone') }}" placeholder="Phone Number" required="required">
                    @error('phone')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
            </label>
        </div>
        <div class="col-md-12">
            <label>
                <span class="text-input">
                    <input class="@error('email') is-invalid @enderror" name="email" type="text"
                        value="{{ old('email') }}" placeholder="Email" required="required">
                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
            </label>
        </div>
        <div class="col-md-12">
            <label>
                <span class="text-input">
                    <textarea class="@error('message') is-invalid @enderror" name="message" rows="4"
                        placeholder="Leave a comment or query" required="required">{{ old('message') }}</textarea>
                    @error('message')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </span>
            </label>
        </div>
        <div class="col-lg-12">
            <div class="pt-15 mb_20 text-center">
                <button id="submit" class="btn btn--md btn--shape-rounded btn--fill btn--primary z-index-1 w-100"
                    type="submit">SEND NOW!</button>
            </div>
        </div>
    </div>
</form>
<script src="https://www.google.com/recaptcha/api.js?render={{ config('recaptcha.site_key') }}"></script>
<script>
    var ReCaptchaCallbackV3 = function() {
        grecaptcha.ready(function() {
            grecaptcha.execute('{{ config('recaptcha.site_key') }}').then(function(token) {
                document.getElementById("recaptcha_token").value = token;
            });
        });
    };
    ReCaptchaCallbackV3()
</script>

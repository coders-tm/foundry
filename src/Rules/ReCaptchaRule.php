<?php

namespace Foundry\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;

class ReCaptchaRule implements Rule
{
    const URL = 'https://www.google.com/recaptcha/api/siteverify';

    const BOT_SCORE = 0.5;

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $response = Http::asForm()->post(static::URL, [
            'secret' => config('recaptcha.secret_key'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ])->json();

        // Explicit parentheses prevent the ?? false from applying to the wrong sub-expression.
        // Score threshold 0.5 is the Google-recommended minimum; 0.0 accepts almost all bots.
        $minScore = config('recaptcha.min_score', 0.5);

        return ($response['success'] ?? false) === true
            && ($response['score'] ?? 0) >= $minScore;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('The verification process for reCAPTCHA failed. Please attempt again.');
    }
}

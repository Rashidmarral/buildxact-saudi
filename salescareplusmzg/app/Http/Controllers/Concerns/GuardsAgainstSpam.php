<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Lightweight bot deterrence for public forms (Contact, Newsletter) without
 * a CAPTCHA: a hidden honeypot field real users never see/fill, plus a
 * minimum-time trap since the form was rendered (bots that submit instantly
 * are almost always scripted). Neither requires a third-party service.
 */
trait GuardsAgainstSpam
{
    protected function isSpamSubmission(Request $request, int $minSeconds = 2): bool
    {
        if (filled($request->input('website'))) {
            return true;
        }

        $renderedAt = (int) $request->input('form_rendered_at');

        if ($renderedAt <= 0 || (time() - $renderedAt) < $minSeconds) {
            return true;
        }

        return false;
    }
}

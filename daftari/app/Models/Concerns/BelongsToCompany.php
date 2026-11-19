<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();

            if ($user->company_id) {
                $builder->where($builder->getModel()->getTable().'.company_id', $user->company_id);

                return;
            }

            // Security audit finding SEC-03: a platform-level account with
            // no company_id (super_admin, admin_staff, partner — all real
            // roles on this same `users` table/guard) used to fall through
            // this check entirely, leaving every tenant-scoped model
            // completely UNSCOPED for them — not restricted to their own
            // (nonexistent) company, but open to every company's data,
            // with no intent behind it at all (not even for the two
            // legitimate admin roles below — it was simply never scoped).
            //
            // super_admin and admin_staff are trusted here deliberately:
            // the entire /admin/* route group is already gated to exactly
            // these two roles (routes/web.php, `role:super_admin,
            // admin_staff` middleware) before any controller runs, and a
            // full-suite run after initially making this scope deny-by-
            // default for every tenantless account (including these two)
            // broke dozens of existing, intentional admin features —
            // listing pages, relation loads, and route-bound lookups all
            // across the admin panel — proving those two roles were
            // always meant to see every company's data this way, not
            // through a withoutGlobalScopes() call repeated at every site.
            // `partner` (and any future tenantless role) gets no such
            // trust and sees nothing by default, which is the fix.
            if ($user->isSuperAdmin() || $user->isAdminStaff()) {
                return;
            }

            $builder->whereRaw('1 = 0');
        });

        static::creating(function ($model) {
            if (empty($model->company_id) && Auth::check() && Auth::user()->company_id) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

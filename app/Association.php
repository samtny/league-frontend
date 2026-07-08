<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

class Association extends Model
{
    use SoftDeletes;

    protected $fillable = array('name', 'user_id', 'subdomain', 'home_image_path', 'about', 'rules_file_path', 'favicon_metadata');

    /**
     * The single place that turns a request host into a subdomain string.
     * Shared by every place that used to reimplement this parsing.
     */
    public static function subdomainFromHost(string $host): ?string {
        return Arr::first(explode('.', $host));
    }

    public static function findBySubdomain(string $host): ?self {
        return static::where('subdomain', static::subdomainFromHost($host))->first();
    }

    /**
     * Resolve "the current association" for a request: an explicit
     * route-model-bound {association} parameter if present, otherwise the
     * subdomain of the request host.
     *
     * Deliberately self-contained (no dependency on a request attribute
     * set by middleware): Laravel constructs controllers once, early,
     * purely to read their getMiddleware() declarations, before the route
     * middleware pipeline (e.g. ResolveAssociation) actually runs - and
     * reuses that same instance for the real dispatch. A controller
     * constructor that only trusts a middleware-populated attribute would
     * see it unset. Called both by ResolveAssociation and by
     * AssociationAwareController's constructor for that reason.
     */
    public static function resolveForRequest($request): ?self {
        $bound = $request->route('association');

        return $bound instanceof self ? $bound : static::findBySubdomain($request->getHost());
    }

    public function user() {
        return $this->hasOne('User');
    }

    public function divisions() {
        return $this->hasMany('App\Division');
    }

    public function teams() {
        return $this->hasMany('App\Team');
    }

    public function venues() {
        return $this->hasMany('App\Venue');
    }

    public function series() {
        return $this->hasMany('App\Series');
    }

    public function activeSeries() {
        return $this->series()->where('archived', 0);
    }

    public function archivedSeries() {
        return $this->series()->where('archived', 1);
    }

    public function schedules() {
        return $this->hasMany('App\Schedule');
    }

    public function activeSchedules() {
        return $this->schedules()
            ->where('archived', '!=', 1)
            ->orWhereNull('archived');
    }

    public function archivedSchedules() {
        return $this->schedules()
            ->where('archived', '=', 1);
    }

    public function resultSubmissions() {
        return $this->hasMany('App\ResultSubmission');
    }

    public function rounds() {
        return $this->hasManyThrough('App\Round', 'App\Schedule', 'association_id', 'schedule_id', 'id', 'id');
    }

    public function activeRounds() {
        return $this->rounds()
            ->where('rounds.start_date', '>=', date('Y-m-d', strtotime('today -7 days')))
            ->where('rounds.start_date', '<=', date('Y-m-d', strtotime('now +7 days')) );
    }

    public function users() {
        return $this->hasManyThrough('App\User', 'App\AssociationUser', 'association_id', 'id', 'id', 'user_id');
    }

    public function contactSubmissions() {
        return $this->hasMany('App\ContactSubmission');
    }

    public function activeContactSubmissions() {
        return $this->contactSubmissions()
            ->where('archived', '!=', 1)
            ->orWhereNull('archived');
    }

}

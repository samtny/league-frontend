<?php

namespace App\Http\Controllers;

use App\Schedule;
use App\Services\PinballMapClient;

class AssociationFrontendController extends AssociationAwareController
{
    public function home()
    {
        if (! empty($this->association)) {
            return view('association.home', ['association' => $this->association]);
        } else {
            abort(404);
        }
    }

    public function about()
    {
        return view('association.about', ['association' => $this->association]);
    }

    public function rules()
    {
        return view('association.rules', ['association' => $this->association]);
    }

    public function css()
    {
        $content = '';

        if (! empty($this->association) && ! empty($this->association->subdomain)) {
            $path = public_path('css/association/'.$this->association->subdomain.'.css');

            if (file_exists($path)) {
                $content = file_get_contents($path);
            }
        }

        $response = \Response::make($content);
        $response->header('Content-Type', 'text/css');
        $response->header('Cache-Control', 'public, max-age=31536000, immutable');

        return $response;
    }

    public function standings()
    {
        return view('association.standings', [
            'association' => $this->association,
            'schedules' => $this->association->activeSchedules,
        ]);
    }

    public function standingsArchive()
    {
        return view('association.standings.archive', [
            'association' => $this->association,
            'schedules' => $this->association->archivedSchedules,
        ]);
    }

    public function schedule()
    {
        return view('association.schedule', [
            'association' => $this->association,
            'schedules' => $this->association->activeSchedules,
        ]);
    }

    public function scheduleFull($string, Schedule $schedule)
    {
        return view('association.schedule.full', [
            'association' => $this->association,
            'schedule' => $schedule,
        ]);
    }

    public function roster()
    {
        $teams = $this->association->teams()
            ->where('active', true)
            ->with('homeVenue', 'division.schedules')
            ->get()
            ->filter(function ($team) {
                return ! empty($team->division) && $team->division->schedules->contains(fn ($schedule) => $schedule->archived != 1);
            });

        return view('association.roster', [
            'association' => $this->association,
            'teams' => $teams,
        ]);
    }

    public function venues(PinballMapClient $pinballMap)
    {
        $activeSchedules = $this->association->activeSchedules;

        $venues = $this->association->venues()
            ->where('active', true)
            ->with('divisions')
            ->orderBy('name')
            ->get()
            ->filter(function ($venue) use ($activeSchedules) {
                return $activeSchedules->contains(function ($schedule) use ($venue) {
                    return $schedule->division_id === null
                        ? $venue->divisions->isEmpty()
                        : $venue->divisions->contains('id', $schedule->division_id);
                });
            })
            ->values();

        $venues->each(function ($venue) use ($pinballMap) {
            $venue->games = $pinballMap->machinesForLocation($venue->pinballmap_id);
        });

        return view('association.venues-directory', [
            'association' => $this->association,
            'venues' => $venues,
        ]);
    }
}

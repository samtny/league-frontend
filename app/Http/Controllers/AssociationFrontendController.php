<?php

namespace App\Http\Controllers;

use App\Schedule;
use App\Services\PinballMapClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

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

    public function standings(Request $request)
    {
        $schedules = $this->activeSchedulesWithDivision();
        $divisions = $this->availableDivisions($schedules);
        $filter = $this->resolveDivisionFilter($request, $divisions);

        if ($filter instanceof RedirectResponse) {
            return $filter;
        }

        return view('association.standings', [
            'association' => $this->association,
            'schedules' => $this->filterSchedulesByDivision($schedules, $filter),
            'divisions' => $divisions,
            'selectedDivision' => $filter,
        ]);
    }

    public function standingsArchive()
    {
        return view('association.standings.archive', [
            'association' => $this->association,
            'schedules' => $this->association->archivedSchedules,
        ]);
    }

    public function schedule(Request $request)
    {
        $schedules = $this->activeSchedulesWithDivision();
        $divisions = $this->availableDivisions($schedules);
        $filter = $this->resolveDivisionFilter($request, $divisions);

        if ($filter instanceof RedirectResponse) {
            return $filter;
        }

        return view('association.schedule', [
            'association' => $this->association,
            'schedules' => $this->filterSchedulesByDivision($schedules, $filter),
            'divisions' => $divisions,
            'selectedDivision' => $filter,
        ]);
    }

    public function scheduleFull($string, Schedule $schedule)
    {
        return view('association.schedule.full', [
            'association' => $this->association,
            'schedule' => $schedule,
        ]);
    }

    private function activeSchedulesWithDivision(): Collection
    {
        return $this->association->activeSchedules()->with('division')->get();
    }

    private function availableDivisions(Collection $schedules): Collection
    {
        return $schedules->pluck('division')
            ->filter()
            ->unique('id')
            ->sort(fn ($a, $b) => [$a->sequence === null, $a->sequence, $a->name]
                <=> [$b->sequence === null, $b->sequence, $b->name])
            ->values();
    }

    private function resolveDivisionFilter(Request $request, Collection $divisions)
    {
        if ($divisions->count() < 2) {
            return null;
        }

        $validIds = $divisions->pluck('id')->map(fn ($id) => (string) $id);
        $fallback = (string) $divisions->first()->id;

        if ($request->has('division')) {
            $requested = (string) $request->query('division');
            $value = $requested === 'all' || $validIds->contains($requested)
                ? $requested
                : $fallback;

            Cookie::queue('division_filter', $value, 525600);

            return redirect()->to($request->url());
        }

        $cookieValue = (string) $request->cookie('division_filter');
        $value = $cookieValue === 'all' || $validIds->contains($cookieValue)
            ? $cookieValue
            : $fallback;

        if ($value !== $cookieValue) {
            Cookie::queue('division_filter', $value, 525600);
        }

        return $value;
    }

    private function filterSchedulesByDivision(Collection $schedules, ?string $filter): Collection
    {
        if ($filter === null || $filter === 'all') {
            return $schedules;
        }

        return $schedules->where('division_id', (int) $filter)->values();
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

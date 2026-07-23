@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.view', $association) }}
@endsection

@section('title', $association->name)

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?></h1>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="row">
                <div class="col mb-3">
                    <h2 class="text-muted">Active Rounds</h2>
                    <?php $roundsByDivision = $association->activeRounds->sortBy('start_date')->groupBy(function ($round) { return $round->division ? $round->division->id : 0; }); ?>
                    <?php if (!$association->activeRounds->isEmpty()): ?>
                        <?php foreach ($association->divisions->sortBy('sequence') as $division): ?>
                            <?php if ($roundsByDivision->has($division->id)): ?>
                                <h3 class="text-muted"><?php echo $division->name; ?></h3>
                                <div class="list-group mb-3">
                                <?php foreach ($roundsByDivision[$division->id] as $round): ?>
                                <a class="list-group-item list-group-item-action" href="{{ route('round.edit', ['association' => $association, 'schedule' => $round->schedule, 'round' => $round]) }}">
                                    <?php echo !empty($round->series) ? $round->series->name : '[no series]'; ?> - <?php echo $round->name; ?> - <?php echo $round->start_date->format('m-d-Y'); ?>
                                </a>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($roundsByDivision->has(0)): ?>
                            <h3 class="text-muted">(no division)</h3>
                            <div class="list-group mb-3">
                            <?php foreach ($roundsByDivision[0] as $round): ?>
                            <a class="list-group-item list-group-item-action" href="{{ route('round.edit', ['association' => $association, 'schedule' => $round->schedule, 'round' => $round]) }}">
                                <?php echo !empty($round->series) ? $round->series->name : '[no series]'; ?> - <?php echo $round->name; ?> - <?php echo $round->start_date->format('m-d-Y'); ?>
                            </a>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                    <div class="list-group">
                        There are no active rounds.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <h2 class="text-muted">Score Submissions</h2>
                    <div class="list-group">
                    <?php $submissions = $association->resultSubmissions->where('approved', 0); ?>
                    <?php if(!($submissions->isEmpty())): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('result_submissions.list', ['association' => $association]) }}">
                        Score Submissions
                        <span class="badge bg-primary rounded-pill"><?php echo count($submissions); ?></span>
                    </a>
                    <?php else: ?>
                    There are no score submissions to review.
                    <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <h2 class="text-muted">Active Schedules</h2>
                    <?php $schedulesByDivision = $association->activeSchedules->sortBy([['series.name', 'asc'], ['name', 'asc']])->groupBy(function ($schedule) { return $schedule->division ? $schedule->division->id : 0; }); ?>
                    <?php if (!$association->activeSchedules->isEmpty()): ?>
                        <?php foreach ($association->divisions->sortBy('sequence') as $division): ?>
                            <?php if ($schedulesByDivision->has($division->id)): ?>
                                <h3 class="text-muted"><?php echo $division->name; ?></h3>
                                <div class="list-group mb-3">
                                <?php foreach ($schedulesByDivision[$division->id] as $schedule): ?>
                                <a class="list-group-item list-group-item-action" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $schedule]) }}">
                                    <?php echo !empty($schedule->series) ? $schedule->series->name : '[no series]'; ?> - <?php echo $schedule->name; ?>
                                </a>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($schedulesByDivision->has(0)): ?>
                            <h3 class="text-muted">(no division)</h3>
                            <div class="list-group mb-3">
                            <?php foreach ($schedulesByDivision[0] as $schedule): ?>
                            <a class="list-group-item list-group-item-action" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $schedule]) }}">
                                <?php echo !empty($schedule->series) ? $schedule->series->name : '[no series]'; ?> - <?php echo $schedule->name; ?>
                            </a>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                    <div class="list-group">
                        There are no active schedules.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="row">
                <div class="col mb-3">
                    <h2 class="text-muted">Administer</h2>
                    <div class="list-group">
                        <a class="list-group-item list-group-item-action" href="{{ route('association.edit', ['association' => $association]) }}">Settings</a>
                        <a class="list-group-item list-group-item-action" href="{{ route('association.divisions', ['association' => $association]) }}">Divisions</a>
                        <a class="list-group-item list-group-item-action" href="{{ route('association.venues', ['association' => $association]) }}">Venues</a>
                        <a class="list-group-item list-group-item-action" href="{{ route('association.teams', ['association' => $association]) }}">Teams</a>
                        <a class="list-group-item list-group-item-action" href="{{ route('association.users', ['association' => $association]) }}">Users</a>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <h2 class="text-muted">Scheduling</h2>
                    <div class="list-group">
                        <a class="list-group-item list-group-item-action" href="{{ route('association.series', ['association' => $association]) }}">Manage Series, Schedules, Rounds</a>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <h2 class="text-muted">Messages</h2>
                    <div class="list-group">
                    <?php $messages = $association->contactSubmissions->where('archived', 0); ?>
                    <?php if(!($messages->isEmpty())): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('contact_submissions.list', ['association' => $association]) }}">
                        Contact Submissions
                        <span class="badge bg-primary rounded-pill"><?php echo count($messages); ?></span>
                    </a>
                    <?php else: ?>
                    There are no messages waiting.
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="rounds row">

    </div>
@endsection

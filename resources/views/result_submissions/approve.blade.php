@extends('layouts.admin')

@section('title', __(':association — Results', ['association' => $association->name]))

@section('breadcrumb')
    {{ Breadcrumbs::render('result_submissions.approve', $association) }}
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="title m-b-md h1">
                {{ __(':association — Results', ['association' => $association->name]) }}
            </div>
        </div>
    </div>
    <div class="result-submissions row">
        <?php foreach ($association->series as $item): ?>
            <div class="col-md-12">
                <h2><?php echo $item->name; ?></h2>
                <div class="list-group">

                </div>
                <?php foreach ($item->schedules as $schedule): ?>
                    <h3><?php echo $schedule->name . '[Schedule Name Here]'; ?></h3>
                    <div class="list-group">
                    <?php foreach ($schedule->matches as $match): ?>
                        <?php foreach ($match->resultSubmissions as $submission): ?>
                        <div class="list-group-item">
                            Home Team Score: <?php echo $submission->home_team_score; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
@endsection

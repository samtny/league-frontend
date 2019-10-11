<?php

// Home > About
Breadcrumbs::for('about', function ($trail) {
    $trail->parent('home');
    $trail->push('About', route('about'));
});

// Home > Blog
Breadcrumbs::for('blog', function ($trail) {
    $trail->parent('home');
    $trail->push('Blog', route('blog'));
});

// Home > Blog > [Category]
Breadcrumbs::for('category', function ($trail, $category) {
    $trail->parent('blog');
    $trail->push($category->title, route('category', $category->id));
});

// Home > Blog > [Category] > [Post]
Breadcrumbs::for('post', function ($trail, $post) {
    $trail->parent('category', $post->category);
    $trail->push($post->title, route('post', $post->id));
});

Breadcrumbs::for('home', function ($trail) {
    $trail->push('Home', url('/'));
});

Breadcrumbs::for('admin', function ($trail) {
    $trail->parent('home');
    $trail->push('Admin', route('admin'));
});

Breadcrumbs::for('admin.users', function ($trail) {
    $trail->parent('admin');
    $trail->push('User Management', route('admin.users'));
});

Breadcrumbs::for('association.view', function ($trail, $association) {
    $trail->parent('home');
    $trail->push($association->name, route('association.view', $association));
});

Breadcrumbs::for('association.create', function ($trail) {
    $trail->parent('admin');
    $trail->push('Create Association', route('association.create'));
});

Breadcrumbs::for('association.edit', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push('Edit', route('association.edit', $association));
});

Breadcrumbs::for('association.divisions', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push('Divisions', route('association.divisions', $association));
});

Breadcrumbs::for('division.create', function ($trail, $association) {
    $trail->parent('association.divisions', $association);
    $trail->push('Create Division', route('division.create', ['association' => $association]));
});

Breadcrumbs::for('division.edit', function ($trail, $association, $division) {
    $trail->parent('association.divisions', $association);
    $trail->push('Edit Division', route('division.edit', ['association' => $association, 'division' => $division]));
});

Breadcrumbs::for('association.teams', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push('Teams', route('association.teams', $association));
});

Breadcrumbs::for('team.create', function ($trail, $association) {
    $trail->parent('association.teams', $association);
    $trail->push('Create Team', route('team.create', ['association' => $association]));
});

Breadcrumbs::for('team.edit', function ($trail, $team) {
    $trail->parent('association.teams', $team->association);
    $trail->push('Edit Team', route('team.edit', ['association' => $team->association, 'team' => $team]));
});

Breadcrumbs::for('association.venues', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push('Venues', route('association.venues', $association));
});

Breadcrumbs::for('venue.create', function ($trail, $association) {
    $trail->parent('association.venues', $association);
    $trail->push('Create Venue', route('venue.create', ['association' => $association]));
});

Breadcrumbs::for('venue.edit', function ($trail, $venue) {
    $trail->parent('association.venues', $venue->association);
    $trail->push('Edit Venue', route('venue.edit', ['association' => $venue->association, 'venue' => $venue]));
});

Breadcrumbs::for('association.series', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push('Series', route('association.series', $association));
});

Breadcrumbs::for('association.users', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push('Users', route('association.users', $association));
});

Breadcrumbs::for('association.user.add', function ($trail, $association) {
    $trail->parent('association.users', $association);
    $trail->push('Add User', route('association.user.add', $association));
});

Breadcrumbs::for('association.user.view', function ($trail, $association, $user) {
    $trail->parent('association.users', $association);
    $trail->push($user->name, route('association.user.view', ['association' => $association, 'user' => $user]));
});

Breadcrumbs::for('association.user.edit', function ($trail, $association, $user) {
    $trail->parent('association.user.view', $association, $user);
    $trail->push('Edit', route('association.user.edit', ['association' => $association, 'user' => $user]));
});

Breadcrumbs::for('association.user.token', function ($trail, $association, $user) {
    $trail->parent('association.user.view', $association, $user);
    $trail->push('Token', route('association.user.token', ['association' => $association, 'user' => $user]));
});

Breadcrumbs::for('series.view', function ($trail, $series) {
    $trail->parent('association.series', $series->association);
    $trail->push($series->name, route('series.view', $series));
});

Breadcrumbs::for('series.edit', function ($trail, $series) {
    $trail->parent('series.view', $series);
    $trail->push($series->name, route('series.edit', $series));
});

Breadcrumbs::for('series.schedules', function ($trail, $series) {
    $trail->parent('series.view', $series);
    $trail->push(__('Schedules'), route('series.schedules', $series));
});

Breadcrumbs::for('schedule.create', function ($trail, $series) {
    $trail->parent('series.schedules', $series);
    $trail->push(__('Create'), route('schedule.create', $series));
});

Breadcrumbs::for('schedule.view', function ($trail, $schedule) {
    $trail->parent('series.schedules', $schedule->series);
    $trail->push($schedule->name ? $schedule->name : '<noname>', route('schedule.view', $schedule));
});

Breadcrumbs::for('schedule.edit', function ($trail, $schedule) {
    $trail->parent('schedule.view', $schedule);
    $trail->push(__('Edit'), route('schedule.edit', $schedule));
});

Breadcrumbs::for('schedule.rounds', function ($trail, $schedule) {
    $trail->parent('schedule.view', $schedule);
    $trail->push(__('Rounds'), route('schedule.rounds', $schedule));
});

Breadcrumbs::for('round.view', function ($trail, $schedule, $round) {
    $trail->parent('schedule.rounds', $schedule);
    $trail->push(!empty($round->start_date) ? $round->start_date : '<empty>');
});

Breadcrumbs::for('round.create', function ($trail, $schedule) {
    $trail->parent('schedule.rounds', $schedule);
    $trail->push(__('Create'), route('round.create', ['schedule' => $schedule]));
});

Breadcrumbs::for('round.edit', function ($trail, $schedule, $round) {
    $trail->parent('round.view', $schedule, $round);
    $trail->push(__('Edit'), route('round.edit', ['schedule' => $schedule, 'round' => $round]));
});

Breadcrumbs::for('results.edit', function ($trail, $schedule) {
    $trail->parent('series.edit', $schedule->series);
    $trail->push(__('Results - :start_date', ['start_date' => date('Y-m-d', strtotime($schedule->start_date))]), route('results.edit', $schedule));
});

Breadcrumbs::for('result_submissions.approve', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push(__('Score Submissions', ['association', $association]));
});

Breadcrumbs::for('association.contact_submissions', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push(__('Messages'), route('contact_submissions.list', ['association', $association]));
});

Breadcrumbs::for('contact_submission.view', function ($trail, $association, $contactSubmission) {
    $trail->parent('association.contact_submissions', $association);
    $trail->push(__('Submission'), route('contact_submission.view', ['association' => $association, 'contactSubmission' => $contactSubmission]));
});

Breadcrumbs::for('series.index', function ($trail, $series) {
    $trail->parent('association.view', $series->association);

});

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
    $trail->parent('admin');
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

Breadcrumbs::for('association.teams', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push('Teams', route('association.teams', $association));
});

Breadcrumbs::for('association.venues', function ($trail, $association) {
    $trail->parent('association.view', $association);
    $trail->push('Venues', route('association.venues', $association));
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

Breadcrumbs::for('association.user.edit', function ($trail, $association, $user) {
    $trail->parent('association.users', $association);
    $trail->push('Edit User', route('association.user.edit', ['association' => $association, 'user' => $user]));
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

Breadcrumbs::for('schedule.view', function ($trail, $schedule) {
    $trail->parent('series.schedules', $schedule->series);
    $trail->push($schedule->name, route('schedule.view', $schedule));
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
    $trail->push($round->start_date);
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

Breadcrumbs::for('series.index', function ($trail, $series) {
    $trail->parent('association.view', $series->association);

});

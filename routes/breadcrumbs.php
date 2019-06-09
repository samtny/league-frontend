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

Breadcrumbs::for('association', function ($trail, $association) {
    $trail->parent('admin');
    $trail->push($association->name, route('association.view', $association));
});

Breadcrumbs::for('association.edit', function ($trail, $association) {
    $trail->parent('association', $association);
    $trail->push('Edit', route('association.edit', $association));
});

Breadcrumbs::for('association.divisions', function ($trail, $association) {
    $trail->parent('association', $association);
    $trail->push('Divisions', route('association.divisions', $association));
});

Breadcrumbs::for('association.teams', function ($trail, $association) {
    $trail->parent('association', $association);
    $trail->push('Teams', route('association.teams', $association));
});

Breadcrumbs::for('association.venues', function ($trail, $association) {
    $trail->parent('association', $association);
    $trail->push('Venues', route('association.venues', $association));
});

Breadcrumbs::for('association.series', function ($trail, $association) {
    $trail->parent('association', $association);
    $trail->push('Series', route('association.series', $association));
});

Breadcrumbs::for('series.edit', function ($trail, $series) {
    $trail->parent('association.series', $series->association);
    $trail->push($series->name, route('series.edit', $series));
});

Breadcrumbs::for('schedule.edit', function ($trail, $schedule) {
    $trail->parent('series.edit', $schedule->series);
    $trail->push('Schedule', route('schedule.edit', $schedule));
});

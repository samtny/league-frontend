<?php

// Home
Breadcrumbs::for('home', function ($trail) {
    $trail->push('Home', route('home'));
});

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

Breadcrumbs::for('admin', function ($trail) {
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

Breadcrumbs::for('association.series', function ($trail, $association) {
    $trail->parent('association', $association);
    $trail->push('Series', route('association.series', $association));
});

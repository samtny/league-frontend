@extends('layouts.full', ['name' => 'rules'])

@section('title', 'Rules')

@section('favicon')
    @parent
@endsection

@section('content')
    @component('components/page-title')
        @slot('title')
            {{ 'Rules' }}
        @endslot
    @endcomponent
    <div class="association-rules-file">
        Help yourself to the <a class="link" href="/storage/<?php echo $association->rules_file_path; ?>" alt="<?php echo $association->name . ' rules' ?>">rules</a>
    </div>
@endsection

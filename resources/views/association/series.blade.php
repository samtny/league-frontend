@extends('layouts.admin')

@section('title', 'test')

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?></h1>
    </div>

@endsection

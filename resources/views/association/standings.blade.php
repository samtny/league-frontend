@extends('layouts.full', ['name' => 'standings'])

@section('content')
    @component('page-title')
        @slot('title')
            Standings - <?php echo $association->name; ?>
        @endslot
    @endcomponent
    <div class="standings">
        TBD
    </div>
@endsection

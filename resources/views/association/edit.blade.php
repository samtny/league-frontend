@extends('layouts.admin')

@section('title', 'Edit Association')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.edit', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?></h1>
    </div>
    <div class="form row">
        <form class="col" method="POST" action="{{ route('association.update', ['association' => $association]) }}" enctype="multipart/form-data">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-item">
                <label for="user_id">Owner</label>
                <select id="user_id" name="user_id">
                    <option value="<?php echo($current_user->id); ?>"><?php echo($current_user->name); ?></option>
                </select>
            </div>

            <input type="hidden" name="id" value="{{ $association->id }}">

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-item">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ $association->name }}" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            @can ('administer-subdomains')
                <div class="form-item">
                    <label for="name">Subdomain</label>
                    <input id="subdomain" type="text" name="subdomain" value="{{ $association->subdomain }}" class="@error('subdomain') is-invalid @enderror">
                    @error('subdomain')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                </div>
            @endcan

            <div class="form-group">
                <label for="home_image_file">Homepage Image File</label>
                <input type="file" id="home_image_file" name="home_image_file" />
            </div>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
    <div class="series">
        Association Series
        <?php if (!empty($series)): ?>
            <?php foreach ($series as $index => $item): ?>
                <a href="{{ route('series.edit', ['series' => $item]) }}">
                    <?php echo ('<div class="series">' . $item->name . '</div>'); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Series for this association.
            </div>
        <?php endif; ?>
    </div>
    <div class="divisions">
        Association Divisions
        <?php if (!empty($divisions)): ?>
            <?php foreach ($divisions as $item): ?>
                <a href="{{ route('division.edit', ['association' => $association, 'division' => $item]) }}">
                    <?php echo ('<div class="division">' . $item->name . '</div>'); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Divisions for this association.
            </div>
        <?php endif; ?>
    </div>
    <div class="venues">
        Association Venues
        <?php if (!empty($venues)): ?>
            <?php foreach ($venues as $item): ?>
                <a href="{{ route('venue.edit', ['association' => $association, 'venue' => $item]) }}">
                    <?php echo ('<div class="venue">' . $item->name . '</div>'); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No venues for this association.
            </div>
        <?php endif; ?>
    </div>
    <div class="teams">
        Association Teams
        <?php if (!empty($association->teams)): ?>
            <?php foreach ($association->teams as $item): ?>
                <a href="{{ route('team.edit', ['association' => $association, 'team' => $item]) }}">
                    <?php echo ('<div class="team">' . $item->name . '</div>'); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No teams for this association.
            </div>
        <?php endif; ?>
    </div>
    <div class="links">
        <a href="{{ route('series.create') }}">Create Series</a>
        <a href="{{ route('division.create', ['association' => $association ]) }}">Create Division</a>
        <a href="{{ route('venue.create', ['association' => $association ]) }}">Create Venue</a>
        <a href="{{ route('team.create', ['association' => $association ]) }}">Create Team</a>
        <a href="{{ route('association.delete', [ 'association' => $association ]) }}">Delete Association</a>
    </div>
@endsection

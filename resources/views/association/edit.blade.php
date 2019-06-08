@extends('layouts.admin')

@section('title', 'Edit Association')

@section('content')
    <div class="title m-b-md">
        Edit Association
    </div>
    <div class="form">
        <form method="POST" action="/association/update" enctype="multipart/form-data">
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
                <a href="/series/<?php echo($item->id); ?>/edit">
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
            <?php foreach ($divisions as $index => $item): ?>
                <a href="/association/<?php echo $association->id; ?>/division/<?php echo($item->id); ?>/edit">
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
            <?php foreach ($venues as $index => $item): ?>
                <a href="/association/<?php echo $association->id; ?>/venue/<?php echo($item->id); ?>/edit">
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
            <?php foreach ($association->teams as $index => $item): ?>
                <a href="/association/<?php echo $association->id; ?>/team/<?php echo($item->id); ?>/edit">
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

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

            @can ('view-association-owner')
            <div class="form-group">
                <label for="user_id">Owner</label>
                <select id="user_id" name="user_id">
                    <option value="<?php echo($current_user->id); ?>"><?php echo($current_user->name); ?></option>
                </select>
            </div>
            @endcan

            <input type="hidden" name="id" value="{{ $association->id }}">

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control" name="name" value="{{ $association->name }}" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            @can ('administer-subdomains')
                <div class="form-group">
                    <label for="name">Subdomain</label>
                    <input id="subdomain" type="text" class="form-control" name="subdomain" value="{{ $association->subdomain }}" class="@error('subdomain') is-invalid @enderror">
                    @error('subdomain')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                </div>
            @endcan

            <div class="form-group">
                <label for="home_image_file">Homepage Image File</label>
                <input type="file" id="home_image_file" name="home_image_file" />
            </div>

            <?php if (!empty($association->subdomain)): ?>
            <div class="form-group">
                <label for="favicon">Favicon</label>
                <input id="favicon" type="file" name="favicon" />
                <small id="faviconHelp" class="form-text text-muted">Generate an icon at https://realfavicongenerator.net/, choose the path "/storage/favicon/<?php echo $association->subdomain; ?>" and upload the archive file here.</small>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <div class="form-group">
                    <input id="submit" type="submit" value="Update"/>
                </div>
            </div>

        </form>
    </div>




    <div class="links">
        <a href="{{ route('association.delete', [ 'association' => $association ]) }}">Delete Association</a>
    </div>
@endsection

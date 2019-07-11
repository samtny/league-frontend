@extends('layouts.admin')

@section('title', __('Messages'))

@section('breadcrumb')
    {{ Breadcrumbs::render('association.contact_submissions', $association) }}
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="title m-b-md h1">
                {{ __('Messages') }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 mb-3">
            <div class="list-group">
                <?php foreach ($association->contactSubmissions as $message): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('contact_submission.view', ['association' => $association, 'contact_submission' => $message]) }}">
                        <?php echo ('<div class="message">' . $message->email . ' — ' . $message->reason . ' — <small>' . $message->comment . '</small></div>'); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

@endsection

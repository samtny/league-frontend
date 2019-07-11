@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('contact_submission.view', $association, $contactSubmission) }}
@endsection

@section('title', __('Submission'))

@section('content')
    <div class="row">
        <h1 class="col">Message</h1>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="email">
                <a href="mailto::{{ $contactSubmission->email }}">{{ $contactSubmission->email }}</a>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="reason">
                {{ $contactSubmission->reason }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="comment">
                {{ $contactSubmission->comment }}
            </div>
        </div>
    </div>
@endsection

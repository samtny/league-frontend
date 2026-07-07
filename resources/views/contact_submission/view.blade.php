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
        <div class="form col">
            <form method="POST" action="{{ route('contact_submission.archive', ['association' => $association, 'contactSubmission' => $contactSubmission]) }}">
                @csrf

                <div class="mb-3 email">
                    <label for="email">Email</label>
                    <a name="email" class="form-control" href="mailto:{{ $contactSubmission->email }}">{{ $contactSubmission->email }}</a>
                </div>

                <div class="mb-3 reason">
                    <label for="reason">Contact Reason</label>
                    <div name="reason" class="form-control">
                        {{ $contactSubmission->reason }}
                    </div>
                </div>

                <div class="mb-3 comment">
                    <label for="comment">Comment</label>
                    <div name="comment" class="form-control">
                        {{ $contactSubmission->comment }}
                    </div>
                </div>

                <div class="form-actions">
                    <div class="mb-3">
                        <a class="btn btn-primary" href="{{ route('contact_submissions.list', ['association' => $association]) }}">Done</a>
                    </div>
                    <div class="mb-3">
                        <input type="submit" class="btn btn-warning" value="Archive">
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

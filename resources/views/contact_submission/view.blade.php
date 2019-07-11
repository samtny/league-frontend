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

                <div class="form-group email">
                    <label for="email">Email</label>
                    <a name="email" class="form-control" href="mailto::{{ $contactSubmission->email }}">{{ $contactSubmission->email }}</a>
                </div>

                <div class="form-group reason">
                    <label for="reason">Contact Reason</label>
                    <div name="reason" class="form-control">
                        {{ $contactSubmission->reason }}
                    </div>
                </div>

                <div class="form-group comment">
                    <label for="comment">Comment</label>
                    <div name="comment" class="form-control">
                        {{ $contactSubmission->comment }}
                    </div>
                </div>

                <div class="form-actions">
                    <div class="form-group">
                        <a class="btn btn-primary" href="{{ route('contact_submissions.list', ['association' => $association]) }}">Done</a>
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-warning" value="Archive">
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'Edit Division')

@section('content')
    <div class="title m-b-md">
        Edit Division
    </div>
    <div class="form">
        <form method="POST" action="{{ route('division.update', ['association' => $association, 'division' => $division])}}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-item">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ $division->name }}" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <span class="form-item-help">Enter a name for this Division, like <em>"A Division"</em></span>
            </div>

            <div class="form-item">
                <label for="sequence">Sequence</label>
                <input id="sequence" type="text" name="sequence" value="{{ $division->sequence }}" class="@error('sequence') is-invalid @enderror">
                @error('sequence')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <span class="form-item-help">Use this to order divisions in display, e.g. <em>"1"</em>, <em>"2"</em>, etc., or leave empty for alpha display.</span>
            </div>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection

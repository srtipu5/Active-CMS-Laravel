@extends('frontend.layouts.app')

@section('content')

     <div class="alert alert-danger text-center">
        @if(isset($errorMessage))
           <h1 style="color:red;">{{ $errorMessage }}</h1>
        @endif
    </div>
@endsection


@extends('frontend.layouts.app')

@section('content')

    <form action="{{ route('bkash.checkout') }}" method="POST">
      @csrf
      <button type="submit" id="bKash_button" class="d-none">Pay With bKash</button>
    </form>

@endsection

@section('script')
 
    <script type="text/javascript">
        $(document).ready(function(){
            $('#bKash_button').trigger('click');
        });
    </script>
@endsection

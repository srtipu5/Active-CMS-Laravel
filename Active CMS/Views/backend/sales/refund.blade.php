@extends('backend.layouts.app')

@section('content')

 <div style="text-align: center;">
     <form action="/admin/bkash/refund" method="POST">
         @csrf
         <div class="form-group">
             <input type="text" class="form-control" name="orderCode" placeholder="Order Code" required >
         </div>
    
         <div class="form-group">
             <input type="submit" class="btn btn-success" value="Click Here For Refund">
         </div>
</div>
@if(isset($result_data_array))
<div style="text-align: center;">
    @foreach($result_data_array as $key => $val)
    @if($key == 'originalTrxID' || $key == 'refundTrxID' || $key == 'amount')
      <div class="form-group">
        <label for="{{$key}}">{{$key}}</label>
        <input type="text" class="alert alert-success" readonly value="{{$val}}">
      </div>
      @endif
    @endforeach    
</div>
@endif

@if(isset($errorMessage))
<div style="text-align: center;">
      <div class="form-group">
        <input type="text" class="alert alert-danger" readonly value="{{$errorMessage}}">
      </div>
</div>
@endif

@endsection

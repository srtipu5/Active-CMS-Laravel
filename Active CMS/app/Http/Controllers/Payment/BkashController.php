<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Models\CombinedOrder;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\SellerPackageController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\CheckoutController;
use Session;
use URL;
use App\Models\Order;

class BkashController extends Controller
{
    private $base_url;
    public function __construct()
    {
        if(get_setting('bkash_sandbox', 1)){
            $this->base_url = "https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/";
        }
        else {
            $this->base_url = "https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/";
        }
    }

    public function pay(){
        $amount = 0;
        if(Session::has('payment_type')){
            if(Session::get('payment_type') == 'cart_payment'){
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                $amount = round($combined_order->grand_total);
            }
            elseif (Session::get('payment_type') == 'wallet_payment') {
                $amount = round(Session::get('payment_data')['amount']);
            }
            elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $amount = round($customer_package->amount);
            }
            elseif (Session::get('payment_type') == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $amount = round($seller_package->amount);
            }
        }

        Session::put('payment_amount', $amount);

        return view('frontend.bkash.index');
    }
    
    public function auth(){
        $request_data = array('app_key'=> env('BKASH_CHECKOUT_APP_KEY'), 'app_secret'=>env('BKASH_CHECKOUT_APP_SECRET'));
        $request_data_json=json_encode($request_data);

        $header = array(
                'Content-Type:application/json',
                'username:'.env('BKASH_CHECKOUT_USER_NAME'),
                'password:'.env('BKASH_CHECKOUT_PASSWORD')
                );
        
        $url = curl_init($this->base_url.'checkout/token/grant');
        curl_setopt($url,CURLOPT_HTTPHEADER, $header);
        curl_setopt($url,CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url,CURLOPT_POSTFIELDS, $request_data_json);
        curl_setopt($url,CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $resultdata = curl_exec($url);
        curl_close($url);

        $token = json_decode($resultdata)->id_token;
        return $token;
    }

    public function checkout(Request $request){
        
        Session::forget('bkash_token');
        Session::put('bkash_token', $this->auth());
        $auth = Session::get('bkash_token');
        
        $website_url = URL::to("/");

        $requestbody = array(
            'mode' => '0011',
            'payerReference' => ' ',
            'callbackURL' => $website_url.'/bkash/callback',
            'amount' => Session::get('payment_amount'),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => "Inv".Session::get('combined_order_id')
        );
        $requestbodyJson = json_encode($requestbody);

        $header = array(
            'Content-Type:application/json',
            'Authorization:' . $auth,
            'X-APP-Key:'.env('BKASH_CHECKOUT_APP_KEY')
        );

        $url = curl_init($this->base_url.'checkout/create');
        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $resultdata = curl_exec($url);
        curl_close($url);

        return redirect((json_decode($resultdata)->bkashURL));
    }
    
    public function callback(Request $request)
    {
        $allRequest = $request->all();
        if(isset($allRequest['status']) && $allRequest['status'] == 'failure'){
            return view('frontend.bkash.fail')->with([
                'errorMessage' => 'Payment Failure'
            ]);

        }else if(isset($allRequest['status']) && $allRequest['status'] == 'cancel'){
            return view('frontend.bkash.fail')->with([
                'errorMessage' => 'Payment Cancelled'
            ]);

        }else{
            
            $resultdata = $this->execute($allRequest['paymentID']);
            Session::forget('payment_details');
            Session::put('payment_details', $resultdata);

            $result_data_array = json_decode($resultdata,true);
    
            if(array_key_exists("statusCode",$result_data_array) && $result_data_array['statusCode'] != '0000'){
                return view('frontend.bkash.fail')->with([
                    'errorMessage' => $result_data_array['statusMessage'],
                ]);
            }else if(array_key_exists("message",$result_data_array)){
                // if execute api failed to response
                sleep(1);
                $resultdata = $this->query($allRequest['paymentID']);
                
                Session::forget('payment_details');
                Session::put('payment_details', $resultdata);
            }
    
            return redirect('/bkash/success');

        }

    }
 
    public function execute($paymentID){
    
        $auth = Session::get('bkash_token');
        
         $requestbody = array(
            'paymentID' => $paymentID
        );
        $requestbodyJson = json_encode($requestbody);

        $header = array(
            'Content-Type:application/json',
            'Authorization:' . $auth,
            'X-APP-Key:'.env('BKASH_CHECKOUT_APP_KEY')
        );

        $url = curl_init($this->base_url.'checkout/execute');
        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $resultdata = curl_exec($url);
        curl_close($url);
        
        return $resultdata;
    }
    
     public function query($paymentID){
    
        $auth = Session::get('bkash_token');
        
         $requestbody = array(
            'paymentID' => $paymentID
        );
        $requestbodyJson = json_encode($requestbody);

        $header = array(
            'Content-Type:application/json',
            'Authorization:' . $auth,
            'X-APP-Key:'.env('BKASH_CHECKOUT_APP_KEY')
        );

        $url = curl_init($this->base_url.'checkout/payment/status');
        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $resultdata = curl_exec($url);
        curl_close($url);
        
        return $resultdata;
    }


    public function success(Request $request){
        $payment_type = Session::get('payment_type');

        if ($payment_type == 'cart_payment') {
            return (new CheckoutController)->checkout_done(Session::get('combined_order_id'), Session::get('payment_details'));
        }
        if ($payment_type == 'wallet_payment') {
            return (new WalletController)->wallet_payment_done(Session::get('payment_data'), $request->payment_details);
        }
        if ($payment_type == 'customer_package_payment') {
            return (new CustomerPackageController)->purchase_payment_done(Session::get('payment_data'), $request->payment_details);
        }
        if($payment_type == 'seller_package_payment') {
            return (new SellerPackageController)->purchase_payment_done(Session::get('payment_data'), $request->payment_details);
        }
    }
    
    public function getRefund(Request $request){
        return view('backend.sales.refund');
        
    }
    
    public function refund(Request $request)
    {
        try {
            Session::forget('bkash_token');
            Session::put('bkash_token', $this->auth());
            $auth = Session::get('bkash_token');
        
            $orders = Order::orderBy('id', 'desc');
            $order = $orders->where('orders.code', '=', $request->orderCode)->first()->payment_details;
            
            $order_array = json_decode($order,true);
    
            if(!isset($order_array['trxID'])){
                 return View('backend.sales.refund')->with('errorMessage', "Something Went Wrong !!");
            }
        
            $requestbody = array(
                'paymentID' => $order_array['paymentID'],
                'amount' => $order_array['amount'],
                'trxID' => $order_array['trxID'],
                'sku' => 'sku',
                'reason' => 'Quality issue'
                );
            $requestbodyJson = json_encode($requestbody);
            
            $header = array(
                'Content-Type:application/json',
                'Authorization:' . $auth,
                'X-APP-Key:'.env('BKASH_CHECKOUT_APP_KEY')
                );
                
            $url = curl_init($this->base_url.'checkout/payment/refund');
            curl_setopt($url, CURLOPT_HTTPHEADER, $header);
            curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
            curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            $resultdata = curl_exec($url);
            curl_close($url);
        
            $result_data_array = json_decode($resultdata,true);
            return View('backend.sales.refund')->with('result_data_array', $result_data_array);
        }catch(Exception $e){
            return View('backend.sales.refund')->with('errorMessage', "Something Went Wrong !!");

        }
    
    }
    
    public function getRefundSatus(Request $request){
        return view('backend.sales.refundStatus');
        
    }
     public function refundStatus(Request $request)
    {
        try {
            Session::forget('bkash_token');
            Session::put('bkash_token', $this->auth());
            $auth = Session::get('bkash_token');
        
            $orders = Order::orderBy('id', 'desc');
            $order = $orders->where('orders.code', '=', $request->orderCode)->first()->payment_details;
    
            $order_array = json_decode($order,true);
            
            if(!isset($order_array['trxID'])){
                 return View('backend.sales.refund')->with('errorMessage', "Something Went Wrong !!");
            }
        
        
            $requestbody = array(
                'paymentID' => $order_array['paymentID'],
                'trxID' => $order_array['trxID']
                );
            $requestbodyJson = json_encode($requestbody);
            
            $header = array(
                'Content-Type:application/json',
                'Authorization:' . $auth,
                'X-APP-Key:'.env('BKASH_CHECKOUT_APP_KEY')
                );
                
            $url = curl_init($this->base_url.'checkout/payment/refund');
            curl_setopt($url, CURLOPT_HTTPHEADER, $header);
            curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
            curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            $resultdata = curl_exec($url);
            curl_close($url);
        
            $result_data_array = json_decode($resultdata,true);
            return View('backend.sales.refund')->with('result_data_array', $result_data_array);
        }catch(Exception $e){
            return View('backend.sales.refund')->with('errorMessage', "Something Went Wrong !!");

        }
        
    }
    
    
}

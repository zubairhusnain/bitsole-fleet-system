<?php
namespace App\Services;
use App\Models\CreditHistory;
use App\Models\Devices;
use App\Models\User;
use DOMDocument;
class StripeService {
    public $headers;
   public $url = 'https://api.stripe.com/v1/';
   public $method = null;
   public $fields = '';
   public $token;
   public function __construct()
   {
       $this->token = env('STRIPE_SECRET', 'sk_test_REDACTED');
       $this->headers = array(
         'Content-Type: application/x-www-form-urlencoded', // for define content type that is json
         'Authorization: Bearer ' . $this->token, // send token in header request
       );
   }

   public function call()
   {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

      switch ($this->method) {
         case "POST":
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($this->fields)
               curl_setopt($ch, CURLOPT_POSTFIELDS, $this->fields);
            break;
         case "PUT":
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            if ($this->fields)
               curl_setopt($ch, CURLOPT_POSTFIELDS, $this->fields);
            break;
        case "DELETE":
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            if ($this->fields)
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->fields);
            break;
         default:
            if ($this->fields) {
               $query = (is_array($this->fields) || is_object($this->fields))
                   ? http_build_query($this->fields)
                   : (string) $this->fields;
               $this->url = sprintf("%s?%s", $this->url, $query);
            }
      }

      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      // Tighten SSL and network behavior for performance and security
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_ENCODING, '');
      $output = curl_exec($ch);
      curl_close($ch);
      $this->fields = '';
      $this->method = "";
      $this->url = 'https://api.stripe.com/v1/';
      return json_decode($output, true); // return php array with api response
   }

    public function make_payment($params)
    {
        $user = userData($params);
        $params = $params->all();
        // dd($params);
        $amount = $params['amount'];
        $description = isset($params['description']) ? $params['description']:"";
        if($user){
            $email = $user->email;
            $params['email'] = $user->email;
            $customerId = $user->customer_stripe_id;
            $name = $user->name;
            if(empty($customerId)){
                $customerId =0;
            }
        }else{
            return response()->json([
                'status' => false,
                'message' => 'User not exist'
            ], 500);
        }

        $response = array();
        $customer_id = 0;
        if($customerId){
            $customer_id = $customerId;
        }else{
            $customer = $this->create_customer($params,$name);
            if(isset($customer['status']) && $customer['status']==true){
                $customer_id = $customer['customerId'];
            }else{
                return response()->json([
                    'status' => false,
                    'message' => $customer['message'],
                ], 500);
            }
        }

        // create customer payment with credit card and plan
        if ($customer_id !==0) {
            $this->url .= 'charges';
            $this->method = "POST";
            $customer = $customer_id;
            $totalAmount = 100 * $amount;
            $this->fields = "amount=$totalAmount&customer=$customer&currency=usd&description=$description";

            $charge = $this->call();
            if(isset($charge['id'])){
                $credits = $amount;
                if(!empty($user->user_credits)){
                    $credits = $amount+$user->user_credits;
                }
                $recharge = CreditHistory::create([
                    'payment_id'=>$charge['id'],
                    'payment_type'=>0,
                    'amount'=>$amount,
                    'status'=>$charge['status'],
                    'device_id'=>NULL,
                    'user_id'=>$user->id,
                ]);
                CreditHistory::where('id',$recharge->id)->update(['reference_id'=>uniqid($recharge->id, false)]);
                User::where('id',$user->id)->update(['customer_stripe_id'=>$customer,'user_credits'=>$credits]);
            }
            if(isset($charge['error'])){
                return response()->json([
                    'status' => false,
                    'message' => $charge['error']['message'],
                ], 500);
            }
            $response['charge'] = $charge;
        }
        return $response;
    }


    public function create_customer($params,$name){
        // $params = $params->all();
        $card_number = $params['cardNumber'];
        $card_month = $params['exp_month'];
        $card_year = $params['exp_year'];
        $cvc = $params['cvc_number'];
        $email = $params['email'];
        $description = isset($params['description']) ? $params['description']:"";
        $this->url .= 'tokens';
        $this->method = "POST";
        $this->fields = "card[number]=$card_number&card[exp_month]=$card_month&card[exp_year]=$card_year&card[cvc]=$cvc";
        $token = $this->call();
        $customerId = 0;
        $customer = [];
        if(isset($token['error'])){
            $customer['status'] = false;
            $customer['message'] = $token['error']['message'];
        }
        if(isset($token['id'])){
            $this->url .= 'customers';
            $this->method = "POST";
            $token = $token['id'];
            $country =$params['address']['country'];
            $line1 =$params['address']['line1'];
            $line2 =$params['address']['line2'];
            $city =$params['address']['city'];
            $postal_code =$params['address']['postal_code'];

            $this->fields = "address[country]=$country&address[line1]=$line1&address[line2]=$line2&address[city]=$city&address[postal_code]=$postal_code&=email=$email&name=$name&source=$token&description=$description";
            $customer = $this->call();
            if(isset($customer['error'])){
                $customer['status'] = false;
                $customer['message'] = $customer['error']['message'];
            }else{
                $customer['customerId'] = isset($customer['id'])? $customer['id']:0;
                $customer['status'] = true;
            }
        }
        return $customer;
    }
    public function start_subscription($params)
    {
        $user = userData($params);
        $params = $params->all();
        $pricing_id = $params['pricing_id'];
        if($user){
            $email = $user->email;
            $params['email'] = $user->email;
            $customerId = $user->customer_stripe_id;
            $name = $user->name;
            if(empty($customerId)){
                $customerId =0;
            }
        }else{
            return response()->json([
                'status' => false,
                'message' => 'User not exist'
            ], 500);
        }

        $response = array();
        $customer_id = 0;
        if($customerId){
            $customer_id = $customerId;
        }else{
            $customer = $this->create_customer($params,$name);
            if(isset($customer['status']) && $customer['status']==true){
                $customer_id = $customer['customerId'];
            }else{
                return response()->json([
                    'status' => false,
                    'message' => $customer['message'],
                ], 500);
            }
        }

        // create customer payment with credit card and plan
        if ($customer_id !==0) {
            $customer = $customer_id;
            $this->url .= 'subscriptions';
            $this->method = "POST";
            $this->fields = "customer=$customer&items[0][price]=$pricing_id";
            $subscription = $this->call();

            if(isset($subscription['error'])){
                return response()->json([
                    'status' => false,
                    'message' => $subscription['error']['message'],
                ], 500);
            }
            if(isset($subscription['id'])){
                User::where('id',$user->id)->update(['customer_subscription_id'=>$subscription['id']]);
            }
            $response['subscription'] = $subscription;
        }
        return $response;
    }

    public function get_payment_list($limit,$offset)
    {
        $params = "";
        if($limit!==""){
            $params.= "?limit=$limit";
            if($offset !==""){
                $params.= $offset;
            }
        }
        $this->url .= 'charges'.$params;
        $this->method = "GET";
        $response = array();
        // create customer payment with credit card and plan
        $this->fields = "";
        $charge = $this->call();

        if(isset($charge['error'])){
            $response['error'] = $charge['error'];
            return $response;
        }
        $response['charge'] = $charge;
        return $response;
    }

    public function get_product_list($userData)
    {
        $params = "?active=true";
        $this->url .= 'products'.$params;
        $this->method = "GET";
        $response = array();
        $this->fields = "";
        $products = $this->call();
        if(isset($products['error'])){
            $response['error'] = $products['error'];
            return $response;
        }
        $data = [];

        if(isset($products['data']) && count($products['data'])>0){
            foreach($products['data'] as $key=>$value){
                if(!empty($value)){
                    $this->url .= 'prices/'.$value['default_price'];
                    $this->method = "GET";
                    $this->fields = "";
                    $prices = $this->call();
                    $data[$key]=$value;
                    $data[$key]['prices']=$prices;
                    // $data[$key]['subscription']=[];
                    if(isset($prices['id'])){
                        if(!empty($userData) && !empty($userData->customer_subscription_id)){
                            $this->url .= 'subscriptions/'.$userData->customer_subscription_id;
                            $this->method = "GET";
                            $this->fields = "";
                            $subscription = $this->call();
                            $subid = isset($subscription['id']) ? $subscription['id']:0;
                            $sub = $this->check_subscription($userData->id,$subid);
                            if(isset($sub['status']) && $sub['status'] == true){
                                if(isset($subscription['plan']['id']) && $subscription['plan']['id'] == $prices['id']){
                                    $data[$key]['subscription']=$subscription;
                                }
                            }

                        }
                    }
                }
            }
        }
        return $data;
    }

    public function check_subscription($userId,$subid,$price=null)
    {
        $this->url .= 'subscriptions/'.$subid;
        $this->method = "GET";
        $this->fields = "";
        $item = $this->call();

        $response = [];
        $response['status'] = false;
        $response['message'] = '';
        $response['is_active'] = false;
        if(isset($item['error'])){
            $response['status'] = false;
            $response['is_active'] = false;
            $response['message'] = $item['error']['message'];
            return $response;
        }

        if(isset($item['id'])){
            $devices = Devices::where('user_id',$userId)->where('device_type',0)->count();
            $planDevices = isset($item['plan']['transform_usage']['divide_by']) ? $item['plan']['transform_usage']['divide_by']:0;
            if($item['status'] !=="active"){
                $response['status'] = false;
                $response['is_active'] = false;
                $response['message'] = 'Subscription plan has '.$item['status'].' please upgrade your subscription';
                return $response;
            }

            if($devices > $planDevices){
                $response['status'] = false;
                $response['is_active'] = true;
                $response['message'] = "You can't create more than $planDevices devices please upgrade subscription plan";
                return $response;
            }

            $response['status'] = true;
            $response['message'] = "";
            $response['is_active'] = true;
            $response['data'] = $item;
            $response['data']['product'] = isset($item['plan']['product']) ? $this->get_stripe_product($item['plan']['product']) :0;
        }

        return $response;
    }


    public function cancell_subscription($param){
        $response['status'] = false;
        $response['message'] = '';
        if(isset($param->sub_id)){
            $sub_id = $param->sub_id;
            $reason = $param->reason;
        }else{
            $response['status'] = false;
            $response['message'] = 'Field required please try again';
            return $response;
        }
        $this->url .= 'subscriptions/'.$sub_id;
        $this->method = "DELETE";
        $response = array();
        $this->fields = "cancellation_details[feedback]=$reason&invoice_now=true&prorate=true";
        $subscription = $this->call();

        if(isset($subscription['error'])){
            $response['status'] = false;
            $response['message'] = $subscription['error']['message'];
        }
        if(isset($subscription['id'])){
            if(isset($subscription['status']) && $subscription['status']=="canceled"){
                User::where('customer_subscription_id',$sub_id)->update(['customer_subscription_id'=>NULL]);
                $response['status'] = true;
                $response['message'] = 'subscription cancelled successfully';
                $response['data'] = [];
            }
        }
        return $response;
    }

    public function get_stripe_product($id){
        $this->url .= 'products/'.$id;
        $this->method = "GET";
        $response = array();
        $this->fields = "";
        $products = $this->call();
        if(isset($products['error'])){
            $response['status'] = false;
            $response['message'] = $products['error']['message'];
            return $response;
        }
        if(isset($products['id'])){
            $response['status'] = true;
            $response['message'] = '';
            $response['data'] = $products;
            return $response;
        }
        $response['status'] = false;
        $response['message'] = '';
        return $response;
    }
}

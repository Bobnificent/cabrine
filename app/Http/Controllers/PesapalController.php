<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Brian2694\Toastr\Facades\Toastr;
use App\CPU\Helpers;
use App\CPU\CartManager;
use App\CPU\OrderManager;
use App\Model\Order;

class PesapalController extends Controller
{
    const SANDBOX_URL = "https://cybqa.pesapal.com/pesapalv3";
    const LIVE_URL = "https://pay.pesapal.com/v3";

    private static function getServerURL()
    {
        if (env('PESAPAL_MODE', 'SANDBOX') == "LIVE") {
            return self::LIVE_URL;
        }
        return self::SANDBOX_URL;
    }

    private static function registerIPN($type)
    {
        $path = "/api/URLSetup/RegisterIPN";

        $accessToken = self::getAccessToken();

        try {
            // Log::info($data);
            $url = self::getServerURL() . $path;
            $data = [
                "url"  => env('APP_URL') . '/api/v4/payments/' . $type,
                "ipn_notification_type" => "POST"
            ];

            // Log::info($data);
            $response = Http::withHeaders([
                'Authorization' =>  'Bearer ' . $accessToken,
            ])->post($url, $data);

            // Log::info($response);

            if ($response->successful()) {
                // 
                $responseBody = $response->json();
                if ($responseBody['status'] == '200') {
                    return $responseBody['ipn_id'];
                }
                // Log::info($responseBody);

                Log::error("PesaPal API Request Failed::IPN: " . $responseBody['message']);
                // Log::info($responseBody);
                return null;
            } else {
                Log::error('PesaPal API Request Failed::IPN: ' . $response->status());
                return null;
            }
        } catch (\Exception $th) {
            //throw $th;
            Log::error('PesaPal API Request Exception::IPN: ' . $th->getMessage());
            return null;
        }
    }

    public static function generatePaymentReference()
    {
        // Generate a random string of 8 characters for uniqueness
        $randomString = bin2hex(random_bytes(4));

        // Get the current timestamp
        $timestamp = time();

        $user_data = Helpers::get_customer();

        // Combine the current timestamp, random string, userId, and transaction type to create the transaction reference
        $transactionReference = $timestamp . '-' . $user_data['id'] . '-' . $randomString;

        $transactionReference = strtolower($transactionReference);

        return $transactionReference;
    }

    public static function getAccessToken()
    {
        $path = "/api/Auth/RequestToken";

        try {
            // Log::info($data);
            $url = self::getServerURL() . $path;


            $data = [
                "consumer_key" => env('PESAPAL_CONSUMER_KEY'),
                "consumer_secret" => env('PESAPAL_SECRET_KEY')
            ];

            if (env('PESAPAL_MODE', 'SANDBOX') == "SANDBOX") {
                $data = [
                    "consumer_key" => env('PESAPAL_DEV_CONSUMER_KEY'),
                    "consumer_secret" => env('PESAPAL_DEV_SECRET_KEY')
                ];
            }
            // Log::info($data);

            $response = Http::post($url, $data);

            if ($response->successful()) {
                // 
                $responseBody = $response->json();
                if ($responseBody['status'] == '200') {
                    return $responseBody['token'];
                }
                // Log::info($responseBody);

                Log::error("PesaPal API Request Failed::TOKEN: " . $responseBody['message']);
                // Log::info($responseBody);
                return null;
            } else {
                Log::error('PesaPal API Request Failed::TOKEN: ' . $response->status());
                return null;
            }
        } catch (\Exception $th) {
            //throw $th;
            Log::error('PesaPal API Request Exception::TOKEN: ' . $th->getMessage());
            return null;
        }
    }

    public function initialize()
    {

        $discount = session()->has('coupon_discount') ? session('coupon_discount') : 0;
        $order_amount = CartManager::cart_grand_total() - $discount;

        $user_data = Helpers::get_customer();

        $unique_id = OrderManager::gen_unique_id();
        $order_ids = [];

        // Enter the details of the payment
        $transaction = [
            'amount' => $order_amount,
            'email' => $user_data['email'],
            'currency' => Helpers::currency_code(),
            'reference_id' => self::generatePaymentReference(),
            'order_group_id' => $unique_id
        ];

        foreach (CartManager::get_cart_group_ids() as $group_id) {
            $orderData = [
                'payment_method' => 'pesapal',
                'payment_status' => 'unpaid',
                'order_status' => 'pending',
                'transaction_ref' => session('transaction_ref'),
                'order_group_id' => $unique_id,
                'cart_group_id' => $group_id
            ];
            $order_id = OrderManager::generate_order($orderData);
            // echo "<pre>";
            // dd($order_id);
            // echo "</pre>";
            array_push($order_ids, $order_id);
        }
        // echo "<pre>";
        // dd($order_id);
        // echo "</pre>";

        try {
            $data = self::processOrder($order_id);
            // echo "<pre>";
            // dd($data['order_tracking_id']);
            // echo "</pre>";
            if (!is_null($data)) {
                Order::where('id', $order_id)->update([
                    'transaction_ref' => $data['order_tracking_id'],
                    'merchant_reference' => $data['merchant_reference']
                ]);
                $redirectUrl = $data['redirect_url'];
                return redirect()->away($redirectUrl);
            }
            throw new \Exception("Unable to process transaction");
        } catch (\Exception $exception) {
            Toastr::error(translate('configuration_invalid'));
            return back();
        }
    }

    // 
    public static function processOrder($transaction)
    {

        try {

            $discount = session()->has('coupon_discount') ? session('coupon_discount') : 0;
            $order_amount = CartManager::cart_grand_total() - $discount;

            $user_data = Helpers::get_customer();

            // $orderData = Order::where('id', $transaction)->first();
            $ipnId = self::registerIPN("orders/" . $transaction);
            $accessToken = self::getAccessToken();


            $path = "/api/Transactions/SubmitOrderRequest";

            $url = self::getServerURL() . $path;

            $data = [
                "id" => self::generatePaymentReference(),
                "currency" => Helpers::currency_code(),
                "amount" => $order_amount,
                "description" => "Product Purchase",
                "callback_url" => env('APP_URL') . "/pesapal-success",
                "notification_id" => $ipnId,
                "billing_address" => [
                    "email_address" => $user_data['email'] ?: '',
                    "phone_number" => $user_data['phone'] ?: '',
                    "country_code" => "",
                    "first_name" => $user_data['name'] ?: '',
                    "middle_name" => "",
                    "last_name" => "",
                    "line_1" => "",
                    "line_2" => "",
                    "city" => "",
                    "state" => "",
                    "postal_code" => "",
                    "zip_code" => "00256"
                ]
            ];

            Log::info("Order Info ---------------=================");
            // Log::info($data);

            $response = Http::withHeaders([
                'Authorization' =>  'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ])->post($url, $data);

            // Log::info('Bearer ' . $accessToken);

            if ($response->successful()) {
                // 

                $responseBody = $response->json();
                Log::info($responseBody);
                if ($responseBody['status'] == '200') {
                    return $responseBody;
                }

                Log::error("PesaPal API Request Failed::ORDER: " . $responseBody['message']);

                return null;
            } else {
                Log::error('PesaPal API Request Failed::ORDER: ' . $response->status());
                return null;
            }
        } catch (\Exception $th) {
            //throw $th;
            Log::error('PesaPal API Request Exception::ORDER: ' . $th->getMessage());
            return null;
        }
    }


    public static function verifyTransactionStatus($orderTrackingId)
    {


        try {
            $path = "/api/Transactions/GetTransactionStatus?orderTrackingId=" . $orderTrackingId;

            $url = self::getServerURL() . $path;

            $accessToken = self::getAccessToken();

            $response = Http::withHeaders([
                'Authorization' =>  'Bearer ' . $accessToken,
            ])->get($url);

            if ($response->successful()) {
                // 
                $responseBody = $response->json();
                Log::info($responseBody);

                if ($responseBody['status'] == '200') {
                    return $responseBody;
                }
                Log::error("PesaPal API Request Failed::STATUS: " . $responseBody['message']);
                return null;
            } else {
                Log::error('PesaPal API Request Failed::STATUS: ' . $response->status());
                return null;
            }
        } catch (\Exception $th) {
            //throw $th;
            Log::error('PesaPal API Request Exception::STATUS: ' . $th->getMessage());
            return null;
        }
    }


    public function pesapalWebhook(Request $request, $transactionId)
    {

        try {

            // validating request object
            if (is_null($request->all())) {
                throw new \Exception("Request body is required");
            }


            Log::error('Pesapal IPN Payload:: ');
            Log::info($request->all());


            $requestBody = $request->all();

            // $queryString = parse_url($requestBody['call_back_url'], PHP_URL_QUERY);

            // parse_str($queryString, $params);

            $orderTrackingId = $requestBody['OrderTrackingId'];

            $transaction = Order::where('transaction_ref', $orderTrackingId)->first();

            if (is_null($transaction)) {
                throw new \Exception("Unable to verify transaction");
            }

            $orderStatusResponse = self::verifyTransactionStatus($transaction->transaction_ref);

            if (is_null($orderStatusResponse)) {
                throw new \Exception('Errors verifying transaction');
            }

            $status = $orderStatusResponse['payment_status_description'];

            Log::info('Pesapal INFO update ::: ' . $status);

            if (strtoupper($status) == 'COMPLETED') {
                // 
                Order::where('transaction_ref', $transaction->transaction_ref)->update([
                    'order_status' => 'confirmed',
                    'payment_status' => 'paid',
                ]);
                // 
            } else {
                // 
                $transaction->order_status = strtoupper($status);
            }

            $transaction->save();


            // {"orderNotificationType":"IPNCHANGE","orderTrackingId":"d0fa69d6-f3cd-433b-858e-df86555b86c8","orderMerchantReference":"1515111111","status":200}


            return response()->json([
                'orderNotificationType' => 'IPNCHANGE',
                'orderTrackingId' => $transaction->transaction_ref,
                'orderMerchantReference' => $transaction->merchant_reference,
                'status' => 200,
            ], 200);
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("Pesapal IPN Webhook:: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 400);
        }
    }
    
    public function success(Request $request){
 
        // $order = Order::where('transaction_ref', $_GET["OrderTrackingId"])->update([
        //     'order_status' => 'confirmed',
        //     'payment_status' => 'paid',
        // ]);
        if (auth('customer')->check()) {
            CartManager::cart_clean();
            Toastr::success('Payment success.');
            return redirect('/account-oder');
        }
        
        return response()->json(['message' => 'Payment succeeded'], 200);
    }
}

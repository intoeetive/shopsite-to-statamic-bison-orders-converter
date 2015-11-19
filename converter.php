<?php

require_once('config.php');

require_once "Spyc.php";

class Converter
{
    
    public function __construct()
    {
        
    }
    
    
    public function run()
    {
        $orders_index = 0;
        $members_index = 0;
        $message = 'Converting finished, result unknown';
        
        //check where there are any orders
        //update starting order #
        $existing_order_files = scandir(CONVERTER_ORDERS_DIR);
        if ($existing_order_files===FALSE)
        {
            $message = 'The orders directory is not valid. Check your config';
            return $message;
        }
        unset($existing_order_files[0]); // .
        unset($existing_order_files[1]); // ..
        if  (($key = array_search('page.md', $existing_order_files)) !== false) {
            unset($existing_order_files[$key]);
        }
        
        $orders_index = count($existing_order_files);
        
        //same for members
        $existing_member_files = scandir(CONVERTER_MEMBERS_DIR);
        if ($existing_member_files===FALSE)
        {
            $message = 'The members directory is not valid. Check your config';
            return $message;
        }
        unset($existing_member_files[0]); // .
        unset($existing_member_files[1]); // ..
        if  (($key = array_search('index.html', $existing_member_files)) !== false) {
            unset($existing_member_files[$key]);
        }
        
        $members_index = count($existing_member_files);
        
        //build array of existing usernames
        $existing_members = array();
        if ($members_index > 0)
        {
            foreach ($existing_member_files as $filename)
            {
                $username = substr($filename, 0, strlen($filename-5)); // strip .yaml
                //$dot_idx = strpos($filename, '.');
                //$member_id = substr($filename, 0, $dot_idx);
                //$username = substr($filename, $dot_idx+1);
                $existing_members[] = $username;
            }
        }
        
        //try to read orders XML file to be imported
        $xml = simplexml_load_file(CONVERTER_SOURCE_XML);
        if ($xml===FALSE)
        {
            $message = 'Could not read orders XML file. Check your config';
            return $message;
        }
        
        foreach ($xml->Order as $order)
        {


            $order_data = array();
            
            $orders_index++;
            //check whether member exists, if not - create one
            
            $username = str_replace('@', 'AT', $order->Billing->Email);
            
            $user_exists = array_search($username, $existing_members);
            if ($user_exists!==FALSE)
            {
                //read member's file
                $member_data = Spyc::YAMLLoad(CONVERTER_MEMBERS_DIR.'/'.$username.'.yaml');
                
            }
            else
            {
                //create member file
                $members_index++;
                $member_data = array(
                  "first_name"=>$order->Billing->NameParts->FirstName,
                  "last_name"=>$order->Billing->NameParts->LastName,
                  "roles"=> array(
                    0=>"member"
                  ),
                  "email"=>$order->Billing->Email,
                  "password"=> "",
                  "password_hash"=> "",
                  "_uid"=> uniqid($this->getRandomString(6)) . $this->getRandomString(8)
                );
                $yaml = Spyc::YAMLDump($member_data);
                $yaml .= "\n---\n";
                $file = fopen(CONVERTER_MEMBERS_DIR.'/'.$username.'.yaml', "w") or die("Unable to write member file!");
                fwrite($file, $yaml);
                fclose($file);
            }
            
            //now build order array
            $order_data['customer_username'] = $username;
            $order_data['customer_uid'] = $member_data['_uid'];
            
            $order_data['subtotal'] = $order->Totals->Subtotal;
            $order_data['shipping'] = $order->Totals->ShippingTotal;
            $order_data['tax'] = $order->Totals->Tax;
            $order_data['total'] = $order->Totals->GrandTotal;
            $order_data['discount'] = (isset($order->Coupon->Total))?$order->Coupon->Total:"0.00";
            
            $order_data['order_date'] = $order->OrderDate;
            $order_data['transaction_id'] = $order->ShopSiteTransactionID;
            $order_data['order_id'] = md5($order->OrderDate);
            
            $order_data['discounts_used'] = '';
            $order_data['coupon_used']= "";
            if (isset($order->Coupon->Name))
            {
                $order_data['coupon_used'] = array(
                    '1'=> array(
                        'title'=>$order->Coupon->Name
                    )
                );
            }
            $order_data['gateway'] = $order->Payment->PaymentGateway;
            $order_data['order_status'] = 'paid';
            $order_data['first_name'] = $order->Billing->NameParts->FirstName;
            $order_data['last_name'] = $order->Billing->NameParts->LastName;
            $order_data['email'] = $order->Billing->Email;
            $order_data['billing_address_1'] = $order->Billing->Address->Street1;
            $order_data['billing_address_2'] = $order->Billing->Address->Street2;
            $order_data['billing_city'] = $order->Billing->Address->City;
            $order_data['billing_state'] = $order->Billing->Address->State;
            $order_data['billing_zip'] = $order->Billing->Address->Code;
            $order_data['billing_country'] = $order->Billing->Address->Country;
            $order_data['shipping_first_name'] = $order->Shipping->NameParts->FirstName;
            $order_data['shipping_last_name'] = $order->Shipping->NameParts->LastName;
            $order_data['shipping_email'] = $order->Billing->Email;
            $order_data['shipping_address_1'] = $order->Shipping->Address->Street1;
            $order_data['shipping_address_2'] = $order->Shipping->Address->Street2;
            $order_data['shipping_city'] = $order->Shipping->Address->City;
            $order_data['shipping_state'] = $order->Shipping->Address->State;
            $order_data['shipping_zip'] = $order->Shipping->Address->Code;
            $order_data['shipping_country'] = $order->Shipping->Address->Country;
            $order_data['shipping_option'] = "";
            $order_data['tax_number'] = "";
            $order_data['custom_data'] = array('phone_number' => $order->Billing->Phone);
            $order_data['username'] = $username;
            $order_data['password_hash'] = "";
            $order_data['title'] = 'Order #'.$orders_index;
            
            $order_data['items'] = array();
            if (!is_array($order->Shipping->Products->Product))
            {
                $products = array($order->Shipping->Products->Product);
            }
            else
            {
                $products = $order->Shipping->Products->Product;
            }
            foreach ($products as $product)
            {
                $item = array(
                    'id'    => $product->Name,
                    'title'    => $product->Name,
                    'price' => $product->ItemPrice,
                    'quantity'  => $product->Quantity,
                    'subtotal'  => $product->Total,
                    'weight'    => $product->Weight
                    
                );
                $order_data['items'][] = $item;
            }
            
            $yaml = Spyc::YAMLDump($order_data);
            $yaml .= "\n---\n";
            $filename = $orders_index.'.'.$order_data['order_id'].'.md';
            $file = fopen(CONVERTER_ORDERS_DIR.'/'.$filename, "w") or die("Unable to write order file!");
            fwrite($file, $yaml);
            fclose($file);

        }
        
        $message = 'Import complete. Created '.$members_index.' members and '.$orders_index.' orders.';
        
        return $message;
    }
    
    public static function getRandomString($length=32, $expanded=false)
    {
        $string = '';
        $characters = "BCDFGHJKLMNPQRSTVWXYZbcdfghjklmnpqrstvwxwz0123456789";
        
        if ($expanded) {
            $characters = "ABCDEFGHIJKLMNPOQRSTUVWXYZabcdefghijklmnopqrstuvwxwz0123456789!@#$%^&*()~[]{}`';?><,./|+-=_";
        }
        
        $upper_limit = strlen($characters) - 1;

        for (; $length > 0; $length--) {
            $string .= $characters{rand(0, $upper_limit)};
        }

        return str_shuffle($string);
    }

}

?>
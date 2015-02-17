<?php

$nzshpcrt_gateways[$num] = array(
  'api_version' => 2.0,
  'name' => 'RBK Money',
  'display_name' => 'RBK Money',
  'internalname' => 'wpsc_merchant_rbkmoney',
  'class_name' => 'wpsc_merchant_rbkmoney',
  'payment_type' => 'rbkmoney',
  'image' => WPSC_URL . '/images/rbkmoney.jpg',
  'form' => 'rbkmoney_form',
  'submit_function' => 'rbkmoney_form_submit',
);

class wpsc_merchant_rbkmoney extends wpsc_merchant {

  var $name = 'RBK Money';

  /*
  Print and submit payment form
  */
  public function submit() {
    $serviceName = '';
    foreach ($this->cart_items as $key => $val) {
      $serviceName .= $val['name'] . '(' . $val['quantity'] . ') ';
    }

    // preparing form data
    $form = array();
    $form['eshopId'] = get_option('rbkmoney_eshopId');
    $form['orderId'] = $this->purchase_id;
    $form['user_email'] = $this->cart_data['email_address'];
    $form['serviceName'] = $serviceName;
    $form['recipientAmount'] = number_format(floatval($this->cart_data['total_price']), 2, '.', '');
    $form['recipientCurrency'] = $this->cart_data['store_currency'];
    $form['successUrl'] = (get_option('rbkmoney_successUrl')) ? get_option('rbkmoney_successUrl') . '&sessionid=' . $this->cart_data['session_id'] : get_option('home') . "/?page_id=7";
    $form['failUrl'] = (get_option('rbkmoney_failUrl')) ? get_option('rbkmoney_failUrl') . '&sessionid=' . $this->cart_data['session_id'] : get_option('home') . "/?page_id=7";


    // calculating hash of order
    $price = str_replace('.', ',', $form['recipientAmount']); // use comma as sum separator
    $hash_string = $form['eshopId'] . "::" . $price . "::" . $form['recipientCurrency'] . "::" . $form['user_email'] . "::" . $form['serviceName'] . "::" . $form['orderId'] . "::::" . get_option('rbkmoney_secretKey');
    $form['hash'] = md5($hash_string);

    $output = "<html>\n<head>\n<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"/>\n</head>\n<body>\n";
    $output .= "<form id=\"paymentform\" action=\"https://rbkmoney.ru/acceptpurchase.aspx\" name=\"paymentform\" method=\"post\">\n";

    foreach ($form as $key => $value) {
      $output .= "<input type=\"hidden\" name=\"" . $key . "\" value=\"" . $value . "\">\n";
    }

    $output .= "</form>\n";
    $output .= "<script type=\"text/javascript\">document.getElementById('paymentform').submit();</script>";
    $output .= "</body>\n</html>";

    echo $output;
    exit();
  }


  /*
   * Processing RBK Money notifications
   */
  function process_gateway_notification() {

    // checking for allowed RBK Money server
    $allowed_ip = array('89.111.188.128', '195.122.9.148');
    if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ip)) {
      exit("IP address {$_SERVER['REMOTE_ADDR']} is not allowed");
    }

    extract(stripslashes_deep($_POST));

    $control_hash = $eshopId . "::" . $orderId . "::" . $serviceName . "::" . $eshopAccount . "::" . $recipientAmount . "::" . $recipientCurrency . "::" . $paymentStatus . "::" . $userName . '::' . $userEmail . '::' . $paymentData . '::' . get_option('rbkmoney_secretKey');

    if ($hash != md5($control_hash)) {
      exit("Hash checking error");
    }

    // change order status
    if ($paymentStatus == 3 || $paymentStatus == 5) {
      $status = ($paymentStatus == 5) ? '3' : '2';
      wpsc_update_purchase_log_status($orderId, $status);
//      echo ok;
    }

  }

}

/*
* Settings form
*/
function rbkmoney_form() {

  $output = "<tr>
      <td>Notification Url:</td>
      <td><input value='" . get_option('home') . "/index.php?wpsc_action=gateway_notification&gateway=wpsc_merchant_rbkmoney' name='wpsc_options[inform_url]' onclick='select(this);' /></td>
  </tr>

  <tr>
      <td>Shop ID:</td>
      <td><input value='" . get_option('rbkmoney_eshopId') . "' name='wpsc_options[rbkmoney_eshopId]' /></td>
  </tr>

   <tr>
      <td>Secret key:</td>
      <td><input value='" . get_option('rbkmoney_secretKey') . "' name='wpsc_options[rbkmoney_secretKey]' /></td>
  </tr>

  <tr>
      <td>Success Url:</td>
      <td><input value='" . get_option('rbkmoney_successUrl') . "' name='wpsc_options[rbkmoney_successUrl]' /></td>
  </tr>

   <tr>
      <td>Fail Url:</td>
      <td><input value='" . get_option('rbkmoney_failUrl') . "' name='wpsc_options[rbkmoney_failUrl]' /></td>
  </tr>";

  return $output;

}


function rbkmoney_form_submit() {

  if (isset($_REQUEST['wpsc_options']['rbkmoney_eshopId'])) {
    update_option('rbkmoney_eshopId', $_REQUEST['wpsc_options']['rbkmoney_eshopId']);
  }
  if (isset($_REQUEST['wpsc_options']['rbkmoney_secretKey'])) {
    update_option('rbkmoney_secretKey', $_REQUEST['wpsc_options']['rbkmoney_secretKey']);
  }
  if (isset($_REQUEST['wpsc_options']['rbkmoney_successUrl'])) {
    update_option('rbkmoney_successUrl', $_REQUEST['wpsc_options']['rbkmoney_successUrl']);
  }
  if (isset($_REQUEST['wpsc_options']['rbkmoney_failUrl'])) {
    update_option('rbkmoney_failUrl', $_REQUEST['wpsc_options']['rbkmoney_failUrl']);
  }

  if (!isset($_REQUEST['wpsc_options']['rbkmoney_form'])) {
    $_REQUEST['wpsc_options']['rbkmoney_form'] = array();
  }
  foreach ($_REQUEST['wpsc_options']['rbkmoney_form'] as $key => $value) {
    update_option(('rbkmoney_form_' . $key), $value);
  }

  return TRUE;
}


?>
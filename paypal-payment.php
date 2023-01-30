<?php 

// Include PayPal SDK and configure API credentials

use PayPal\Api\Agreement;
use PayPal\Api\AgreementStateDescriptor;
use PayPal\Api\ChargeModel;
use PayPal\Api\Currency;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\Plan;

require 'vendor/autoload.php';

$apiContext = new \PayPal\Rest\ApiContext(
  new \PayPal\Auth\OAuthTokenCredential(
    'client_id',
    'secret'
  )
);

// Create a new billing plan
$plan = new Plan();
$plan->setName('Monthly Subscription')
  ->setDescription('Monthly Subscription for premium content')
  ->setType('INFINITE');

// Set up payment definition
$paymentDefinition = new PaymentDefinition();
$paymentDefinition->setName('Regular Payments')
  ->setType('REGULAR')
  ->setFrequency('Month')
  ->setFrequencyInterval('1')
  ->setCycles('0')
  ->setAmount(new Currency(array('value' => 9.99, 'currency' => 'USD')));

// Set up charge models
$chargeModel = new ChargeModel();
$chargeModel->setType('SHIPPING')
  ->setAmount(new Currency(array('value' => 0.0, 'currency' => 'USD')));
$paymentDefinition->setChargeModels(array($chargeModel));

// Set up merchant preferences
$merchantPreferences = new MerchantPreferences();
$merchantPreferences->setReturnUrl('https://www.example.com/return')
  ->setCancelUrl('https://www.example.com/cancel')
  ->setAutoBillAmount('yes')
  ->setInitialFailAmountAction('CONTINUE')
  ->setMaxFailAttempts('0')
  ->setSetupFee(new Currency(array('value' => 9.99, 'currency' => 'USD')));

$plan->setPaymentDefinitions(array($paymentDefinition));
$plan->setMerchantPreferences($merchantPreferences);

// Create the plan
try {
  $createdPlan = $plan->create($apiContext);
  try {
    $agreement = new Agreement();
    $agreement->setName('Monthly Subscription')
      ->setDescription('Monthly Subscription for premium content')
      ->setStartDate(gmdate("Y-m-d\TH:i:s\Z", time() + 3600));

    $plan = Plan::get($createdPlan->getId(), $apiContext);
    $agreement->setPlan($plan);

    // Add payer information
    $payer = new Payer();
    $payer->setPaymentMethod('paypal');
    $agreement->setPayer($payer);

    // Create agreement
    $agreement = $agreement->create($apiContext);

   // Extract approval URL to redirect user
$approvalUrl = $agreement->getApprovalLink();
header("Location: {$approvalUrl}");
exit;
  } catch (Exception $ex) {
    // Handle any errors that may have occurred
    echo "Agreement creation failed: " . $ex->getMessage();
  }
} catch (Exception $ex) {
  // Handle any errors that may have occurred
  echo "Plan creation failed: " . $ex->getMessage();
}

// Update database table when payment is successful
if (isset($_GET['token']) && $_GET['token'] != '') {
  try {
    // Execute agreement
    $agreement = new Agreement();
    $agreement->execute($_GET['token'], $apiContext);
    $agreement = Agreement::get($agreement->getId(), $apiContext);

    // Update database table with agreement information
    $conn = mysqli_connect("host", "username", "password", "database");
    $query = "UPDATE users SET agreement_id = '" . $agreement->getId() . "', agreement_status = '" . $agreement->getState() . "' WHERE user_id = '" . $user_id . "'";
    mysqli_query($conn, $query);
    mysqli_close($conn);

  } catch (Exception $ex) {
    // Handle any errors that may have occurred
    echo "Payment execution failed: " . $ex->getMessage();
  }
}

?>
      

<?
include '../scat.php';
include '../lib/txn.php';
include '../lib/eps-express.php';

$id= (int)$_REQUEST['id'];
$amount= $_REQUEST['amount'];
$from= (int)$_REQUEST['from'];

if (!$id || !$amount)
  die_jsonp("Either transaction or amount was not specified.");
if (!$from)
  die_jsonp("Payment to return from not specified.");

$q= "SELECT cc_txn FROM payment WHERE id = $from";
$cc_txn= $db->get_one($q)
  or die_jsonp("Unable to find transaction information.");

$cc_amount= bcmul($amount < 0 ? -1 : 1, $amount);

$eps= new EPS_Express;
$response= $eps->CreditCardReturn($id, $cc_txn, $cc_amount);

$xml= new SimpleXMLElement($response);

if ($xml->Response->ExpressResponseCode != 0) {
  die_jsonp((string)$xml->Response->ExpressResponseMessage);
}

$method= 'credit';

$cc= array();
$cc['cc_txn']= $xml->Response->Transaction->TransactionID;
$cc['cc_approval']= $xml->Response->Transaction->ApprovalNumber;
$cc['cc_type']= $xml->Response->Card->CardLogo;

$txn= new Transaction($db, $id);

try {
  $payment= $txn->addPayment($method, $amount, $cc);
} catch (Exception $e) {
  die_jsonp($e->getMessage());
}

echo jsonp(array('payment' => $payment,
                 'txn' => txn_load($db, $id),
                 'payments' => txn_load_payments($db, $id)));

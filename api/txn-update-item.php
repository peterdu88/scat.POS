<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];
$id= (int)$_REQUEST['id'];

if (!$txn_id || !$id) die_jsonp('No transaction or item specified');

$txn= txn_load($db, $txn_id);
if ($txn['paid']) {
  die_jsonp("This order is already paid!");
}

$db->start_transaction();

if (isset($_REQUEST['price'])) {
  $price= $_REQUEST['price'];
  if (preg_match('/^\d*(\/|%)$/', $price)) {
    $discount = (float)$price;
    $discount_type = "'percentage'";
    $price= 'IF(item.retail_price, item.retail_price, txn_line.retail_price)';
    $discount_manual= 1;
  } elseif (preg_match('/^\d*\.?\d*$/', $price)) {
    if ($txn['type'] == 'vendor') {
      $price= (float)$price;
      $discount_type= 'NULL';
      $discount= 'NULL';
    } else {
      $discount= 'IF(item.retail_price, ' . (float)$price . ', NULL)';
      $discount_type= 'IF(item.retail_price, "fixed", NULL)';
      $price=    'IF(item.retail_price, item.retail_price, ' . (float)$price . ')';
    }
    $discount_manual= 1;
  } elseif (preg_match('/^(cost)$/', $price)) {
    $discount = "(SELECT MIN(net_price)
                    FROM vendor_item
                   WHERE vendor_item.item = item.id)";
    $discount_type = "'fixed'";
    $price= 'item.retail_price';
    $discount_manual= 1;
  } elseif (preg_match('/^(msrp)$/', $price)) {
    $discount = 'NULL';
    $discount_type = 'NULL';
    $price= 'item.retail_price';
    $discount_manual= 1;
  } elseif (preg_match('/^(def|\.\.\.)$/', $price)) {
    $discount = 'item.discount';
    $discount_type = 'item.discount_type';
    $price= 'item.retail_price';
    $discount_manual= 0;
  } else {
    die_jsonp("Did not understand price.");
  }

  $q= "UPDATE txn_line, item
          SET txn_line.retail_price = $price,
              txn_line.discount_type = $discount_type,
              txn_line.discount = $discount,
              txn_line.discount_manual = $discount_manual
        WHERE txn = $txn_id AND txn_line.id = $id AND txn_line.item = item.id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (!empty($_REQUEST['quantity'])) {
  /* special case: #/# lets us split line with two quantities */
  if (preg_match('!^(\d+)/(\d+)$!', $_REQUEST['quantity'], $m)) {
    $quantity= (int)$m[2] * ($txn['type'] == 'customer' ? -1 : 1);

    $q= "INSERT INTO txn_line (txn, item, ordered, override_name,
                               retail_price, discount_type, discount,
                               discount_manual, taxfree)
         SELECT txn, item, $quantity, override_name,
                retail_price, discount_type, discount, discount_manual, taxfree
           FROM txn_line WHERE txn = $txn_id AND txn_line.id = $id";
    $r= $db->query($q)
      or die_query($db, $q);

    $quantity= (int)$m[1];
  } else {
    $quantity= (int)$_REQUEST['quantity'];
  }

  $q= "UPDATE txn_line
          SET ordered = -1 * $quantity
        WHERE txn = $txn_id AND txn_line.id = $id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['name'])) {
  $name= $db->real_escape_string($_REQUEST['name']);
  $q= "UPDATE txn_line
          SET override_name = IF('$name' = '', NULL, '$name')
        WHERE txn = $txn_id AND txn_line.id = $id";

  $r= $db->query($q)
    or die_query($db, $q);
}

txn_apply_discounts($db, $txn_id)
  or die_jsonp("Failed to apply discounts.");

$db->commit()
  or die_query($db, "COMMIT");

$items= txn_load_items($db, $txn_id);

$txn= txn_load($db, $txn_id);

echo jsonp(array('txn' => $txn, 'items' => $items));

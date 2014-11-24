<?
require 'scat.php';
require 'lib/txn.php';

head("Scat");
?>
<style>
.admin {
  display: none;
}

.choices ul { margin: 0; padding-left: 1.2em; list-style: circle; }
.choices li {
  text-decoration: underline;
  color: #339;
  cursor:pointer;
}
.over {
  font-weight: bold;
  color: #600;
}
.code, .discount, .person {
  font-size: smaller;
}
.dollar:before {
  content: '$';
}

/* Hide/show some elements from paid invoices. */
#txn.paid .remove,
#txn.paid #pay,
#txn.paid .choices, #txn.paid .errors,
#txn.paid #delete
{
  display: none;
}
#txn #return {
  display: none;
}
#txn.paid #return
{
  display: inline;
}

.payment-buttons {
  text-align: right;
}

.pay-method {
  text-align: center;
}

#txn h2 {
  margin-bottom: 0;
}

#notes tr {
  vertical-align: top;
}
</style>
<script>
var Txn = {};

Txn.delete= function (id) {
  $.getJSON("api/txn-delete?callback=?",
            { txn: id },
            function(data) {
              if (data.error) {
                displayError(data);
              } else {
                window.location.href= './';
              }
            });
}

Txn.loadId= function (id) {
  $.getJSON("api/txn-load.php?callback=?",
            { type: "customer",
              id: id },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                loadOrder(data);
              }
              $("#status").text("Loaded sale.").fadeOut('slow');
            });
}

Txn.loadNumber= function(num) {
  $.getJSON("api/txn-load.php?callback=?",
            { type: "customer",
              number: num },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                loadOrder(data);
              }
              $("#status").text("Loaded sale.").fadeOut('slow');
            });
}

var lastItem;

function updateItems(items) {
  $.each(items, function(i,item) {
    var row= $("#txn tbody tr:data(line_id=" + item.line_id + ")");
    if (!row.length) {
      addNewItem(item);
    } else {
      row.data(item);
      updateRow(row);
    }
  });
  updateTotal();
}

function updateRow(row) {
  $('.quantity', row).text(row.data('quantity'));
  if (row.data('quantity') > row.data('stock')) {
    $('.quantity', row).parent().addClass('over');
  } else {
    $('.quantity', row).parent().removeClass('over');
  }
  $('.code', row).text(row.data('code'));
  $('.name', row).text(row.data('name'));
  $('.discount', row).text(row.data('discount'));
  $('.price', row).text(row.data('price').toFixed(2));
  $('.ext', row).text(amount(row.data('ext_price')));
}

function updateValue(row, key, value) {
  var txn= $('#txn').data('txn');
  var line= $(row).data('line_id');
  
  var data= { txn: txn, id: line };
  data[key] = value;

  $.getJSON("api/txn-update-item.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                displayError(data);
              }
              updateOrderData(data.txn);
              updateItems(data.items);
            });
}

function setActiveRow(row) {
  if (lastItem) {
    lastItem.removeClass('active');
  }
  lastItem= row;
  lastItem.addClass('active');
}

$(document).on('dblclick', '.editable', function() {
  // Just stop now if transaction is paid
  if ($('#txn').hasClass('paid')) {
    return false;
  }

  var val= $(this).children('span').eq(0);
  var key= val.attr("class");
  var fld= $('<input type="text">');
  fld.val(val.text());
  fld.attr("class", key);
  fld.width($(this).width());
  fld.data('default', fld.val());

  fld.on('keyup blur', function(ev) {
    // Handle ESC key
    if (ev.type == 'keyup' && ev.which == 27) {
      var val=$('<span>');
      val.text($(this).data('default'));
      val.attr("class", $(this).attr('class'));
      $(this).replaceWith(val);
      return false;
    }

    // Everything else but RETURN just gets passed along
    if (ev.type == 'keyup' && ev.which != '13') {
      return true;
    }

    var row= $(this).closest('tr');
    var key= $(this).attr('class');
    var value= $(this).val();
    var val= $('<span>Updating</span>');
    val.attr("class", key);
    $(this).replaceWith(val);
    updateValue(row, key, value);

    return false;
  });

  val.replaceWith(fld);
  fld.focus().select();
});

$(document).on('click', '.remove', function() {
  var txn= $('#txn').data('txn');
  var id= $(this).closest('tr').data('line_id');

  $.getJSON("api/txn-remove-item.php?callback=?",
            { txn: txn, id: id },
            function(data) {
              if (data.error) {
                displayError(data);
                return;
              }
              var row= $("#txn tbody tr:data(line_id=" + data.removed + ")");
              if (row.is('.active')) {
                lastItem= null;
              }
              row.remove();
              updateOrderData(data.txn);
              updateItems(data.items);
              updateTotal();
            });

  return false;
});

function addItem(item) {
  var txn= $("#txn").data("txn");
  $.getJSON("api/txn-add-item.php?callback=?",
            { txn: txn, item: item.id },
            function(data) {
              if (data.error) {
                displayError(data);
              } else if (data.matches) {
                // this shouldn't happen!
                play("no");
              } else {
                updateOrderData(data.txn);
                updateItems(data.items);
                updateTotal();
              }
            });
}

var protoRow= $('<tr class="item" valign="top"><td><a class="remove"><i class="fa fa-trash-o" title="Remove"></i></a><td align="center" class="editable"><span class="quantity"></span></td><td align="left"><span class="code"></span></td><td class="editable"><span class="name"></span><div class="discount"></div></td><td class="editable dollar" class="right"><span class="price"></span></td><td class="right"><span class="ext"></span></td></tr>');

function addNewItem(item) {
  var row= $("#items tbody tr:data(line_id=" + item.line_id + ")").first();

  if (!row.length) {
    // add the new row
    row= protoRow.clone();
    row.on('click', function() { setActiveRow($(this)); });
    row.appendTo('#items tbody');
  }

  row.data(item);
  updateRow(row);
  setActiveRow(row);
}

var paymentRow= $('<tr class="payment-row"><th colspan=4 class="payment-buttons"></th><th class="payment-method" align="right">Method:</th><td class="payment-amount" align="right">$0.00</td></tr>');

var paymentMethods= {
  cash: "Cash",
  change: "Change",
  credit: "Credit Card",
  square: "Square",
  stripe: "Stripe",
  dwolla: "Dwolla",
  gift: "Gift Card",
  check: "Check",
  discount: "Discount",
  bad: "Bad Debt",
  donation: "Donation",
};

function updateTotal() {
  var total= $("#txn").data("total");
  var subtotal= $("#txn").data("subtotal");
  $('#items #subtotal').text(amount(subtotal));
  var tax_rate= $('#txn').data('tax_rate');
  var tax= total - subtotal;
  $('#items #tax').text(amount(tax));
  $('#items #total').text(amount(total));

  $('.payment-row').remove();

  var paid_date= $('#txn').data('paid_date');
  var paid= $('#txn').data('paid');
  if (paid || paid_date != null) {
    $('#items #due').text(amount(total - paid));
    $('#due-row').show();
  } else {
    $('#due-row').hide();
  }

  var payments= $('#txn').data('payments');
  if (!payments) return;

  $.each(payments, function(i, payment) {
    var row= paymentRow.clone();
    row.data(payment);
    var remove= $('<a class="admin" name="remove"><i class="fa fa-trash-o"></i></a>');
    if (payment.method == 'discount' && payment.discount) {
      $('.payment-method', row).text('Discount (' + payment.discount + '%):');
      remove.removeClass('admin');
    } else {
      $('.payment-method', row).text(paymentMethods[payment.method] + ':');
    }
    $('.payment-amount', row).text(amount(payment.amount));

    if (payment.method == 'credit') {
      $('.payment-buttons', row).append($('<a name="print"><i class="fa fa-print"></i></a>'));
    }
    $('.payment-buttons', row).append(remove);

    $('#due-row').before(row);
  });
}

function updateOrderData(txn) {
  // set transaction data
  $('#txn').data('txn_raw', txn);
  $('#txn').data('txn', txn.id);
  $('#txn').data('subtotal', txn.subtotal)
  $('#txn').data('total', txn.total)
  $('#txn').data('paid', txn.total_paid)
  $('#txn').toggleClass('paid', txn.paid != null);
  $('#txn').data('paid_date', txn.paid)
  var tax_rate= parseFloat(txn.tax_rate).toFixed(2);
  $('#txn').data('tax_rate', tax_rate)
  $('#txn #tax_rate .val').text(tax_rate);
  var type= (txn.total_paid ? 'Invoice' :
             (txn.returned_from ? 'Return' : 'Sale'));
  $('#txn #description').text(type + ' ' +
                              Date.parse(txn.created).toString('yyyy') +
                              '-' + txn.number);
  if (txn.returned_from) {
    var btn= $('<button class="btn btn-xs btn-link"><i class="fa fa-reply"></i></button>');
    btn.on('click', function () {
      Txn.loadId(txn.returned_from);
    });
    $('#txn #description').append(btn);
  }
  $('#txn').data('person', txn.person)
  $('#txn #person .val').text(txn.person_name ? txn.person_name : 'Anonymous');
  var format= 'MMM d yyyy h:mmtt';
  var dates= Date.parse(txn.created).toString(format);
  if (txn.filled) {
//    dates = dates + ' / Filled: ' + Date.parse(txn.filled).toString(format);
  }
  if (txn.paid) {
    dates = dates + ' / Paid: ' + Date.parse(txn.paid).toString(format);
  }
  $('#txn #dates').text(dates);
}

var protoNote= $("<tr><td></td><td></td><td></td></tr>");

function loadOrder(data) {
  updateOrderData(data.txn)

  if (data.payments != undefined) {
    $('#txn').data('payments', data.payments);
  }

  if (data.person != undefined) {
    $('#txn').data('person_raw', data.person);
  }

  if (data.items != undefined) {
    $('#txn').data('items', data.items);

    // dump existing item rows
    $("#items tbody tr").remove();

    // load rows
    $.each(data.items, function(i, item) {
      addNewItem(item);
    });
  }

  // update notes
  if (data.notes != undefined) {
    $('#txn').data('notes', data.notes);
    $("#notes tbody tr").remove();
    $.each(data.notes, function(i, note) {
      var row= protoNote.clone();
      $("td:nth(1)", row).text(note.entered);
      $("td:nth(2)", row).text(note.content);
      $("#notes tbody").append(row);
    });
  }

  updateTotal();
}

function showOpenOrders(data) {
  $('#sales tbody').empty();
  $.each(data, function(i, txn) {
    var row=$('<tr><td>' + txn.number + '</td>' +
              '<td>' + Date.parse(txn.created).toString('d MMM HH:mm') +
              '<div class="person">' + txn.person_name + '</div>' + '</td>' +
              '<td>' + txn.ordered + '</td></tr>');
    row.click(txn, function(ev) {
      $("#status").text("Loading sale...").show();
      $.getJSON("api/txn-load.php?callback=?",
                { id: ev.data.id },
                function (data) {
                  if (data.error) {
                    displayError(data);
                  } else {
                    loadOrder(data);
                  }
                  $("#status").text("Loaded sale.").fadeOut('slow');
                });
    });
    $('#sales tbody').append(row);
  });
  $('#sales').show();
}

function txn_add_payment(options) {
  $.ajax({ type: 'GET',
           url: "api/txn-add-payment.php?callback=?",
           dataType: 'json',
           data: options,
           async: false,
           success: function(data) {
              if (data.error) {
                displayError(data);
              } else {
                updateOrderData(data.txn);
                $('#txn').data('payments', data.payments);
                updateTotal();
                $.modal.close();
                if (options.method == 'credit' && options.amount >= 25.00) {
                  printChargeRecord(data.payment);
                }
              }
           }});
}

function printReceipt() {
  var txn= $('#txn').data('txn');
  if (!txn) {
    displayError("No sale to print.");
    return false;
  }
  var lpr= $('<iframe id="receipt" src="print/receipt.php?print=1&amp;id=' + txn + '"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
  return false;
}

function printInvoice() {
  var txn= $('#txn').data('txn');
  if (!txn) {
    displayError("No sale to print.");
    return false;
  }
  var lpr= $('<iframe id="receipt" src="print/invoice.php?print=1&amp;id=' + txn + '"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
  return false;
}

function printChargeRecord(id) {
  var lpr= $('<iframe id="receipt" src="print/charge-record.php?print=1&amp;id=' + id + '"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
  return false;
}

$(function() {
  $('#txn').data('tax_rate', 0.00);

  $(document).bind('keydown', 'meta+shift+z', function(ev) {
    $('.admin').toggle();
  });
  $(document).bind('keydown', 'meta+p', function(ev) {
    return printReceipt();
  });
  $('input').bind('keydown', 'meta+p', function(ev) {
    return printReceipt();
  });

  $(document).bind('keydown', 'meta+shift+backspace', function(ev) {
    txn= $('#txn').data('txn');
    if (!txn) {
      return;
    }
    Txn.delete(txn);
  });

  $('#lookup').submit(function(ev) {
    ev.preventDefault();
    $("#lookup").removeClass("error");

    $('input[name="q"]', this).focus().select();

    var q = $('input[name="q"]', this).val();

    // short integer and recently scanned? adjust quantity
    var val= parseInt(q, 10);
    if (q.length < 4 && lastItem && val != 0 && !isNaN(val)) {
      updateValue(lastItem, 'quantity', val);
      return false;
    }

    // (%V|@)INV-(\d+) is an invoice to load
    var m= q.match(/^(%V|@)INV-(\d+)/);
    if (m) {
      $.getJSON("api/txn-load.php?callback=?",
                { type: "customer", id: m[2] },
                function (data) {
                  if (data.error) {
                    displayError(data);
                  } else {
                    loadOrder(data);
                  }
                  $("#status").text("Loaded sale.").fadeOut('slow');
                });
      return false;
    }

    txn= $('#txn').data('txn');

    // go find!
    $.ajax({ type: 'GET',
             url: "api/txn-add-item.php?callback=?",
             dataType: 'json',
             data: { txn: txn, q: q },
             async: false,
             success: function(data) {
                if (data.error) {
                  displayError(data);
                } else if (data.matches) {
                  if (data.matches.length == 0) {
                    play("no");
                    $("#lookup").addClass("error");
                    var errors= $('<div class="alert alert-danger"/>');
                    errors.text(" Didn't find anything for '" + q + "'.");
                    errors.prepend('<button type="button" class="close" onclick="$(this).parent().remove(); return false">&times;</button>');
                    $("#items").before(errors);
                  } else {
                    play("maybe");
                    var choices= $('<div class="choices alert alert-warning"/>');
                    choices.prepend('<button type="button" class="close" onclick="$(this).parent().remove(); return false">&times;</button>');
                    var list= $('<ul>');
                    $.each(data.matches, function(i,item) {
                      var n= $("<li>" + item.name + "</li>");
                      n.click(item, function(ev) {
                        addItem(ev.data);
                        $(this).closest(".choices").remove();
                      });
                      list.append(n);
                    });
                    choices.append(list);
                    $("#items").before(choices);
                  }
                } else {
                  updateOrderData(data.txn);
                  play("yes");
                  updateItems(data.items);
                  updateTotal();
                }
              }});

    return false;
  });

  $("#sidebar a[id='unpaid']").click();

<?
  $id= (int)$_REQUEST['id'];
  $number= (int)$_REQUEST['number'];
  if ($number) {
    $q= "SELECT id FROM txn WHERE type = 'customer' AND number = $number";
    $id= $db->get_one($q);
  }

  if ($id) {
    $data= txn_load_full($db, $id);
    echo 'loadOrder(', json_encode($data), ");\n";
  }
?>
});
</script>
<div class="row">
<div class="col-md-3 col-md-push-9 well" id="sidebar">
  <ul class="nav nav-pills nav-justified">
    <li class="active"><a id="unpaid">Unpaid</a></li>
    <li><a id="recent">Recent</a></li>
  </ul>
<script>
$("#sidebar .nav a").click(function() {
  var params= {
    open: { type: 'customer', unfilled: true },
    unpaid: { type: 'customer', unpaid: true },
    recent: { type: 'customer', limit: 20 },
  };
  $("#sales").hide();
  $(this).parent().siblings().removeClass('active');
  $.getJSON("api/txn-list.php?callback=?",
            params[$(this).attr('id')],
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                showOpenOrders(data);
              }
              $("#status").text("Loaded.").fadeOut('slow');
            });
  $(this).parent().addClass('active');
  $("#status").text("Loading...").show();
});
</script>
<table class="table table-condensed table-striped"
       id="sales" style="display: none">
 <thead>
  <tr><th>#</th><th>Date/Name</th><th>Items</th></tr>
 </thead>
 <tbody>
 </tbody>
</table>
<br>
<form id="txn-load">
  <div class="input-group">
    <input type="text" class="form-control"
           name="invoice" size="8"
           placeholder="Invoice">
    <span class="input-group-btn">
      <button class="btn btn-default" type="button">Load</button>
    </span>
  </div>
</form>
<script>
$("#txn-load").submit(function(ev) {
  ev.preventDefault();
  Txn.loadNumber($("#txn-load input[name='invoice']").val());
  return false;
});
</script>
</div>
<div class="col-md-9 col-md-pull-3" id="txn">
<form class="form form-inline" id="lookup">
  <div class="input-group">
    <span class="input-group-addon"><i class="fa fa-barcode"></i></span>
    <input type="text" class="form-control"
           id="autofocus" name="q"
           size="60"
           autocomplete="off" autocorrect="off" autocapitalize="off"
           placeholder="Scan item or enter search terms"
           value="">
    <span class="input-group-btn">
      <input type="submit" class="btn btn-default" value="Find Items">
    </span>
  </div>
</form>
<br>
<div class="panel panel-default">
  <div class="panel-heading">
    <div class="row">
      <div id="sale-buttons" class="col-md-5 col-md-push-7 text-right">
        <button id="invoice" class="btn btn-default">Invoice</button>
        <button id="print" class="btn btn-default">Print</button>
        <button id="delete" class="btn btn-default">Delete</button>
        <button id="pay" class="btn btn-default">Pay</button>
        <button id="return" class="btn btn-default">Return</button>
      </div>
<script>
$("#invoice").on("click", function() {
  printInvoice();
});
$("#print").on("click", function() {
  if ($("#txn").data("paid_date") != null ||
      confirm("Invoice isn't paid. Sure you want to print?"))
  printReceipt();
});
$("#delete").on("click", function() {
  var txn= $('#txn').data('txn');
  Txn.delete(txn);
});
$("#pay").on("click", function() {
  var txn= $('#txn').data('txn');
  $.getJSON("api/txn-allocate.php?callback=?",
            { txn: txn },
            function (data) {
              if (data.error) {
                displayError(data);
              }

              $('#choose-pay-method .optional').hide();

              // Show 'Return Credit Card' if it is possible
              var txn_raw= $('#txn').data('txn_raw');
              if (txn_raw.returned_from) {
                $.getJSON("api/txn-load.php?callback=?",
                          { id: txn_raw.returned_from },
                          function (data) {
                            $.each(data.payments, function(i, payment) {
                              if (payment.method == 'credit' &&
                                  payment.amount > 0 &&
                                  payment.cc_txn != '') {
                                $('#choose-pay-method #credit-refund').show();
                                $('#pay-credit-refund').data('from', payment.id);
                              }
                            });
                          });
              }

              // Show 'Stored Card' if it is possible
              var person= $('#txn').data('person_raw');
              if (person && person.payment_account_id) {
                $('#choose-pay-method #credit-stored').show();
              }

              $("#choose-pay-method #due").val(amount(txn_raw.total -
                                                      txn_raw.total_paid));

              $.modal($("#choose-pay-method"), { persist: true});
            });
});
$("#return").on("click", function() {
  var txn= $('#txn').data('txn');
  if (!txn || !confirm("Are you sure you want to create a return?")) {
    return false;
  }
  $.getJSON("api/txn-return.php?callback=?",
            { txn: txn },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                loadOrder(data);
              }
            });
});
</script>
<style>
#choose-pay-method {
  text-align: center;
}
#choose-pay-method .optional {
  display: none;
}
</style>
<div id="choose-pay-method" style="display: none">
  <div class="panel panel-default">
    <div class="panel-heading">
      <div class="input-group input-group-lg" style="width: 20em; margin: auto">
        <span class="input-group-addon">Due:</span>
        <input type="text" class="form-control" id="due" disabled value="$0.00">
      </div>
    </div>
    <div class="panel-body">
 <button class="btn btn-primary btn-lg" data-value="cash">Cash</button>
<?if ($DEBUG) {?>
 <button id="credit-refund" class="btn btn-default btn-lg optional" data-value="credit-refund">Refund Credit Card</button>
 <button id="credit-stored" class="btn btn-default btn-lg optional" data-value="credit-stored">Stored Credit Card</button>
 <button class="btn btn-default btn-lg" data-value="credit">Credit Card</button>
<?}?>
 <button class="btn btn-default btn-lg" data-value="credit-manual">Credit Card (Manual)</button>
 <br><br>
 <button class="btn btn-default" data-value="gift">Gift Card</button>
 <button class="btn btn-default" data-value="check">Check</button>
 <button class="btn btn-default" data-value="other">Other</button>
 <br><br>
 <button class="btn btn-default" data-value="discount">Discount</button>
 <button class="btn btn-default" data-value="donation">Donation</button>
 <button class="btn btn-default" data-value="bad-debt">Bad Debt</button>
    </div><!-- /.panel-body -->
  </div><!-- /.panel -->
</div><!-- #choose-pay-method -->
<script>
$("#choose-pay-method").on("click", "button", function(ev) {
  var method= $(this).data("value");
  $.modal.close();
  var id= "#pay-" + method;
  var due= ($("#txn").data("total") - $("#txn").data("paid")).toFixed(2);
  $(".amount", id).val(due);
  $.modal($(id), { persist: true, overlayClose: false });
  $(".amount", id).focus().select();
});
</script>
<form role="form" id="pay-cash" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <input type="submit" class="btn btn-primary" name="Pay">
 <button name="cancel" class="btn btn-default">Cancel</button>
</form>
<script>
$("#pay-cash").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-cash .amount").val();
  txn_add_payment({ id: txn, method: "cash", amount: amount, change: true });
});
</script>
<form id="pay-credit-refund" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <input type="submit" value="Refund">
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-credit-refund").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-credit-refund .amount").val();
  var refund_from= $("#pay-credit-refund").data('from');
  $.getJSON("api/cc-refund.php?callback=?",
            { id: txn, amount: parseFloat(amount).toFixed(2),
              from: refund_from },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                updateOrderData(data.txn);
                $('#txn').data('payments', data.payments);
                updateTotal();
                $.modal.close();
              }
            });
});
</script>
<form id="pay-credit-stored" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <input type="submit" value="Pay">
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-credit-stored").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var person= $("#txn").data("person");
  var amount= $("#pay-credit-stored .amount").val();
  $.getJSON("api/cc-stored.php?callback=?",
            { id: txn, amount: parseFloat(amount).toFixed(2),
              person: person },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                updateOrderData(data.txn);
                $('#txn').data('payments', data.payments);
                updateTotal();
                if (amount > 25.00) {
                  printChargeRecord(data.payment);
                }
                $.modal.close();
              }
            });
});
</script>
<form id="pay-credit" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <input type="submit" value="Swipe">
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-credit").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-credit .amount").val();
  $.getJSON("api/cc-begin.php?callback=?",
            { id: txn, amount: parseFloat(amount).toFixed(2) },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                $.modal.close();
                $.modal('<iframe src="' + data.url +
                        '" height=500" width="600" style="border:0">',
                        {
                          closeHTML: "",
                          containerCss: {
                            backgroundColor: "#fff",
                            borderColor: "#fff",
                            height: 520,
                            padding: 0,
                            width: 620,
                          },
                          position: undefined,
                          overlayClose: false,
                        });
              }
            });
});
</script>
<div id="pay-credit-manual" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="Visa">Visa</button>
 <button class="btn btn-default" name="MasterCard">MasterCard</button>
 <button class="btn btn-default" name="Discover">Discover</button>
 <button class="btn btn-default" name="AmericanExpress">American Express</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<script>
$("#pay-credit-manual").on("click", "button", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-credit-manual .amount").val();
  var cc_type= $(this).attr('name');
  if (cc_type == 'cancel') {
    $.modal.close();
    return false;
  }
  txn_add_payment({ id: txn, method: "credit", amount: amount, change: false,
                    cc_type: cc_type });
});
</script>
<form id="pay-other" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" data-value="square">Square</button>
 <button class="btn btn-default" data-value="stripe">Stripe</button>
 <button class="btn btn-default" data-value="dwolla">Dwolla</button>
 <button class="btn btn-default" data-value="cancel">Cancel</button>
</form>
<script>
$("#pay-other").on("click", "button", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-other .amount").val();
  var method= $(this).data('value');
  if (method == 'cancel') {
    $.modal.close();
    return false;
  }
  txn_add_payment({ id: txn, method: method, amount: amount, change: false });
});
</script>
<div id="pay-gift" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="card form-control" type="text" placeholder="Scan or type card number">
 </div>
 <button class="btn btn-default" name="lookup">Check Card</button>
 <button class="btn btn-default" name="old">Old Card</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<div id="pay-gift-complete" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Pay</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<script>
$("#pay-gift").on("click", "button[name='lookup']", function (ev) {
  var txn= $("#txn").data("txn");
  var card= $("#pay-gift .card").val();
  if (card == '...') {
    card= "11111111111"; // Test card.
  }
  $.getJSON("<?=GIFT_BACKEND?>/check-balance.php?callback=?",
            { card: card },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                var due= ($("#txn").data("total") - $("#txn").data("paid"));
                $('#pay-gift-balance').text("Card has $" +
                                            data.balance +
                                            " remaining. Last used " +
                                            data.latest + '.');
                var def= due;
                if (data.balance < due) {
                  def= data.balance;
                }
                if (data.balance - due <= 10.00) {
                  def= data.balance;
                }
                $("#pay-gift-complete .amount").val(def);
                $.modal.close();
                $("#pay-gift-complete").data(data);
                $.modal($("#pay-gift-complete"), { overlayClose: false, persist: true });
              }
            });
});
$("#pay-gift").on("click", "button[name='old']", function (ev) {
  var due= ($("#txn").data("total") - $("#txn").data("paid"));
  var def= due;
  $("#pay-gift-complete .amount").val(def);
  $.modal.close();
  $("#pay-gift-complete").data(null);
  $.modal($("#pay-gift-complete"), { overlayClose: false, persist: true });
});
$("#pay-gift-complete").on("click", "button[name='pay']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-gift-complete .amount").val();
  var card= $("#pay-gift-complete").data('card');
  if (card) {
    $.getJSON("<?=GIFT_BACKEND?>/add-txn.php?callback=?",
              { card: card, amount: -amount },
              function (data) {
                if (data.error) {
                  displayError(data);
                } else {
                  var balance= $("#pay-gift-complete").data('balance');
                  txn_add_payment({ id: txn, method: "gift", amount: amount,
                                    change: (balance - amount <= 10.00) });
                }
              });
  } else {
    txn_add_payment({ id: txn, method: "gift", amount: amount, change: true });
  }
});
</script>
<div id="pay-check" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Pay</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<script>
$("#pay-check").on("click", "button[name='pay']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-check .amount").val();
  txn_add_payment({ id: txn, method: "check", amount: amount, change: false });
});
</script>
<form id="pay-discount" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Discount</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</form>
<script>
$("#pay-discount").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-discount .amount").val();
  txn_add_payment({ id: txn, method: "discount",
                    amount: amount, change: false });
});
</script>
<div id="pay-bad-debt" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Pay</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<script>
$("#pay-bad-debt").on("click", "button[name='pay']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-bad-debt .amount").val();
  txn_add_payment({ id: txn, method: "bad", amount: amount, change: false });
});
</script>
<form id="pay-donation" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Pay</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</form>
<script>
$("#pay-donation").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-donation .amount").val();
  txn_add_payment({ id: txn, method: "donation", amount: amount,
                    change: false });
});
</script>
<script>
$(".pay-method").on("click", "button[name='cancel']", function(ev) {
  ev.preventDefault();
  $.modal.close();
});
</script>
      <div id="details" class="col-md-7 col-md-pull-5">
        <div style="font-size: larger; font-weight: bold"
             id="description">New Sale</div>
        <div id="dates"></div>
        <div id="person">
          <span class="val">Anonymous</span>
          <i id="info-person" class="fa fa-info-circle"></i>
        </div>
      </div>
    </div>
  </div><!-- .panel-heading -->
<script>
$("#txn #person").on("dblclick", function(ev) {
  if (typeof $("#txn").data("txn") == "undefined") {
    return false;
  }

  var fld= $('<input type="text" size="40">');
  fld.val($(".val", this).text());
  fld.data('default', fld.val());

  fld.on('keyup', function(ev) {
    // Handle ESC key
    if (ev.type == 'keyup' && ev.which == 27) {
      var val= $(this).data('default');
      $(this).parent().text(val);
      $(this).remove();
      return false;
    }

    // Everything else but RETURN just gets passed along
    if (ev.type == 'keyup' && ev.which != '13') {
      return true;
    }

    ev.preventDefault();

    $("#person-create input[name='name']").val($(this).val());
    $("#person-create").modal();

    var val= $(this).data('default');
    $(this).parent().text(val);
    $(this).remove();

    return false;
  });

  fld.autocomplete({
    source: "./api/person-list.php?callback=?",
    minLength: 2,
    select: function(ev, ui) {
      $(this).parent().text(ui.item.value);
      $(this).remove();
      $.getJSON("api/txn-update-person.php?callback=?",
                { txn: $("#txn").data("txn"), person: ui.item.id },
                function (data) {
                  if (data.error) {
                    displayError(data);
                    return;
                  }
                  loadOrder(data);
                });
    },
  });

  $(".val", this).empty().append(fld);
  fld.focus().select();
});
$("#txn #info-person").on("click", function(ev) {
  var person= $('#txn').data('person');
  if (!person)
    return false;
  $.getJSON("api/person-load.php?callback=?",
            { person: person },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadPerson(data.person);
              $.modal($('#person-info'));
            });
});
function loadPerson(person) {
  $('#person-info').data('person', person);
  var active= parseInt(person.active);
  $('#person-info #name').text(person.name);
  $('#person-info #company').text(person.company);
  $('#person-info #email').text(person.email);
  $('#person-info #phone').text(person.phone);
  $('#person-info #address').text(person.address);
  $('#person-info #tax_id').text(person.tax_id);
}
</script>
<table id="person-info" style="display: none">
  <tr>
   <th>Name:</th>
   <td><span id="name"></span></td>
  </tr>
  <tr>
   <th>Company:</th>
   <td id="company"></td>
  </tr>
  <tr>
   <th>Email:</th>
   <td id="email"></td>
  </tr>
  <tr>
   <th>Phone:</th>
   <td id="phone"></td>
  </tr>
  <tr>
   <th>Address:</th>
   <td id="address"></td>
  </tr>
  <tr>
   <th>Tax ID:</th>
   <td id="tax_id"></td>
  </tr>
</table>
<form id="person-create" class="form-horizontal" style="display:none">
  <div class="form-group">
    <label for="name" class="col-sm-2 control-label">Name</label>
    <div class="col-sm-10">
      <input type="text" class="form-control" id="name" name="name"
             placeholder="Name">
    </div>
  </div>
  <div class="form-group">
    <label for="company" class="col-sm-2 control-label">Company</label>
    <div class="col-sm-10">
      <input type="text" class="form-control" id="company" name="company"
             placeholder="Company">
    </div>
  </div>
  <div class="form-group">
    <label for="email" class="col-sm-2 control-label">Email</label>
    <div class="col-sm-10">
      <input type="email" class="form-control" id="email" name="email"
             placeholder="Email">
    </div>
  </div>
  <div class="form-group">
    <label for="phone" class="col-sm-2 control-label">Phone</label>
    <div class="col-sm-10">
      <input type="text" class="form-control" id="phone" name="phone"
             placeholder="Phone">
    </div>
  </div>
  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <button type="button" name="cancel" class="btn btn-default">
        Cancel
      </button>
      <input type="submit" class="btn btn-primary" name="Create">
    </div>
  </div>
</form>
<script>
$('#person-create').on('submit', function(ev) {
  ev.preventDefault();

  var data= {
    name: $("input[name='name']", this).val(),
    company: $("input[name='company']", this).val(),
    email: $("input[name='email']", this).val(),
    phone: $("input[name='phone']", this).val(),
  };

  $.getJSON("api/person-add.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              $.getJSON("api/txn-update-person.php?callback=?",
                        { txn: $("#txn").data("txn"), person: data.person },
                        function (data) {
                          if (data.error) {
                            displayError(data);
                            return;
                          }
                          updateOrderData(data.txn);
                          $.modal.close();
                        });
            });
});
$('#person-create').on('click', "button[name='cancel']", function(ev) {
  ev.preventDefault();
  $.modal.close();
});
</script>
<table class="table table-condensed table-striped" id="items">
 <thead>
  <tr><th></th><th>Qty</th><th>Code</th><th width="50%">Name</th><th>Price</th><th>Ext</th></tr>
 </thead>
 <tfoot>
  <tr id="subtotal-row"><th colspan=4></th><th align="right">Subtotal:</th><td id="subtotal" class="right">$0.00</td></tr>
  <tr id="tax-row"><th colspan=4></th><th align="right" id="tax_rate">Tax (<span class="val">0.00</span>%):</th><td id="tax" class="right">$0.00</td></tr>
  <tr id="total-row"><th colspan=4></th><th align="right">Total:</th><td id="total" class="right">$0.00</td></tr>
  <tr id="due-row" style="display:none">
   <th colspan="4" style="text-align: right">
    <a id="lock"><i class="fa fa-lock"></i></a>
   </th>
   <th align="right">
     Due:
   </th>
   <td id="due" class="right">$0.00</td>
  </tr>
 </tfoot>
<script>
$("#items").on("click", ".payment-row a[name='print']", function() {
  var row= $(this).closest(".payment-row");
  printChargeRecord(row.data("id"));
});
$("#items").on("click", ".payment-row a[name='remove']", function() {
  var row= $(this).closest(".payment-row");
  $.getJSON("api/txn-remove-payment.php?callback=?",
            { txn: $("#txn").data("txn"), id: row.data("id"),
              admin: ($(".admin").is(":visible") ? 1 : 0) },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              updateOrderData(data.txn);
              $("#txn").data("payments", data.payments);
              updateTotal();
            });
});
$('#tax_rate .val').editable(function(value, settings) {
  var txn= $('#txn').data('txn');

  $.getJSON("api/txn-update-tax-rate.php?callback=?",
            { txn: txn, tax_rate: value },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              updateOrderData(data.txn);
              updateTotal();
            });
  return "...";
}, { event: 'dblclick', style: 'display: inline' });
$("#lock").on("click", function() {
  $('.admin').toggle();
  $('#lock').toggleClass('fa-lock fa-unlock-alt');
});
</script>
 <tbody>
 </tbody>
</table>
<table id="notes" class="table table-condensed table-striped">
 <thead>
  <tr><th style="width: 20px"><a id="add-note-button" class="fa fa-plus-square-o"></a></th><th style="width: 10em">Date</th><th>Note</th></tr>
 </thead>
 <tbody>
 </tbody>
</table>
<form id="add-note" style="display: none">
  <input type="text" name="note" size="40">
  <input type="submit" value="Add">
</form>
<script>
$("#add-note-button").on("click", function(ev) {
  var txn= $("#txn").data("txn");
  if (!txn) return;
  $.modal($("#add-note"));
});
$("#add-note").on("submit", function(ev) {
  ev.preventDefault();

  var txn= $("#txn").data("txn");
  var note= $('input[name="note"]', this).val();
  $.getJSON("api/txn-add-note.php?callback=?",
            { id: txn, note: note},
            function (data) {
              loadOrder(data);
              $.modal.close();
            });
});
</script>
</div>
</div>
<?foot();

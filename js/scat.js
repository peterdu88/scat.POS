function play(type) {
  var sounds = {
    'yes' : 'Pop',
    'no' : 'Basso',
    'maybe' : 'Bottle',
  };
  if (window.fluid) {
    window.fluid.playSound(sounds[type]);
  }
}

$.getFocusedElement = function() {
  var elem = document.activeElement;
  return $( elem && ( elem.type || elem.href ) ? elem : [] );
};

// http://stackoverflow.com/a/3109234
function round_to_even(num, decimalPlaces) {
  var d = decimalPlaces || 0;
  var m = Math.pow(10, d);
  var n = d ? num * m : num;
  var i = Math.floor(n), f = n - i;
  var r = (f == 0.5) ? ((i % 2 == 0) ? i : i + 1) : Math.round(n);
  return d ? r / m : r;
}

// format number as $3.00 or ($3.00)
function amount(amount) {
  if (typeof(amount) == 'undefined' || amount == null) {
    return '';
  }
  if (typeof(amount) == 'string') {
    amount= parseFloat(amount);
  }
  if (amount < 0.0) {
    return '($' + Math.abs(amount).toFixed(2) + ')';
  } else {
    return '$' + amount.toFixed(2);
  }
}

// display an error message
function displayError(data) {
  if (typeof data != "object") {
    data= { error: data };
  }

  // SimpleModal
  if (typeof $.modal != "undefined") {
    if (!$('#simplemodal-overlay').length) {
      $.modal(data.error);
      return;
    }
  }
  // Bootstrap modal
  else if (!$('body').hasClass('modal-open')) {
    var modal= $('<div class="modal fade"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button><h4 class="modal-title">Error</h4></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div></div>');

    $('.modal-body', modal).text(data.error);

    modal.on('hidden.bs.modal', function() {
      $(this).remove();
    });

    modal.appendTo($('body')).modal();

    return;
  }

  // play("no");

  alert(data.error);
}

$(function() {
  $(document).keydown(function(ev) {
    if (ev.keyCode == 16 || ev.keyCode == 17
        || ev.keyCode == 18 || ev.keyCode == 91
        || ev.metaKey || ev.altKey || ev.ctrlKey) {
      return true;
    }
    var el = $.getFocusedElement();
    if (!el.length) {
      var inp= $('#autofocus', this);
      if (ev.keyCode != 13) {
        inp.val('');
      }
      inp.focus();
    }
  });
});

// http://blog.fawnanddoug.com/2012/05/inline-editor-custom-binding-for.html
ko.bindingHandlers.jeditable = {
  init: function(element, valueAccessor, allBindingsAccessor) {
    // get the options that were passed in
    var options = allBindingsAccessor().jeditableOptions || {};
          
    // "submit" should be the default onblur action like regular ko controls
    if (!options.onblur) {
      options.onblur = 'submit';
    }

    // allow the editable function to be set as an option
    if (!options.onupdate) {
      options.onupdate= function(value, params) {
        valueAccessor()(value);
        return value;
      }
    }

    // set the value on submit and pass the editable the options
    $(element).editable(options.onupdate, options);
 
    //handle disposal (if KO removes by the template binding)
    ko.utils.domNodeDisposal.addDisposeCallback(element, function() {
      //$(element).editable("destroy");
    });
 
  },
      
  //update the control when the view model changes
  update: function(element, valueAccessor, allBindingsAccessor) {
    // get the options that were passed in
    var options = allBindingsAccessor().jeditableOptions || {};

    var value = ko.utils.unwrapObservable(valueAccessor());
    if (options.ondisplay) {
      value= options.ondisplay(value);
    }
    $(element).html(value);
  }
};

$.editable.types['text'].plugin= function(settings, original) {
  $('input', this).addClass('form-control');
}
$.editable.types['select'].plugin= function(settings, original) {
  $('select', this).addClass('form-control');
}
$.editable.types['textarea'].plugin= function(settings, original) {
  $('textarea', this).addClass('form-control');
}

$.fn.editable.defaults.width  = 'none';
$.fn.editable.defaults.height = 'none';

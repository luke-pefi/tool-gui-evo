/**
Address editable input.
Internally value stored as {city: "Moscow", street: "Lenina", building: "15"}

@class address
@extends abstractinput
@final
@example
<a href="#" id="address" data-type="address" data-pk="1">awesome</a>
<script>
$(function(){
    $('#address').editable({
        url: '/post',
        title: 'Enter city, street and building #',
        value: {
            city: "Moscow", 
            street: "Lenina", 
            building: "15"
        }
    });
});
</script>
**/
(function ($) {
    "use strict";
    
    var Address = function (options) {
        this.init('address', options, Address.defaults);
    };

    //inherit from Abstract input
    $.fn.editableutils.inherit(Address, $.fn.editabletypes.abstractinput);

    $.extend(Address.prototype, {
        /**
        Renders input from tpl

        @method render() 
        **/        
        render: function() {
           this.$input = this.$tpl.find('input');
        },
        
        /**
        Default method to show value in element. Can be overwritten by display option.
        
        @method value2html(value, element) 
        **/
        value2html: function(value, element) {
            if(!value) {
                $(element).empty();
                return; 
            }
            var html = $('<div>').text(value.name).html() + ' : ' + $('<div>').text(value.phone).html() + ', <br/>' +
                       $('<div>').text(value.address1).html() + ', ' +
                       $('<div>').text(value.address2).html() + ', ' +
                       $('<div>').text(value.state).html() + ', ' +
                       $('<div>').text(value.zipcode).html() + ', ' +
                       $('<div>').text(value.country).html() + '.';
                        
            $(element).html(html); 
        },
        
        /**
        Gets value from element's html
        
        @method html2value(html) 
        **/        
        html2value: function(html) {        
          /*
            you may write parsing method to get value by element's html
            e.g. "Moscow, st. Lenina, bld. 15" => {city: "Moscow", street: "Lenina", building: "15"}
            but for complex structures it's not recommended.
            Better set value directly via javascript, e.g. 
            editable({
                value: {
                    city: "Moscow", 
                    street: "Lenina", 
                    building: "15"
                }
            });
          */ 
          return null;  
        },
      
       /**
        Converts value to string. 
        It is used in internal comparing (not for sending to server).
        
        @method value2str(value)  
       **/
       value2str: function(value) {
           var str = '';
           if(value) {
               for(var k in value) {
                   str = str + k + ':' + value[k] + ';';  
               }
           }
           return str;
       }, 
       
       /*
        Converts string to value. Used for reading value from 'data-value' attribute.
        
        @method str2value(str)  
       */
       str2value: function(str) {
           /*
           this is mainly for parsing value defined in data-value attribute. 
           If you will always set value by javascript, no need to overwrite it
           */
           return str;
       },                
       
       /**
        Sets value of input.
        
        @method value2input(value) 
        @param {mixed} value
       **/         
       value2input: function(value) {
           if(!value) {
             return;
           }
           this.$input.filter('[name="name"]').val(value.name);
           this.$input.filter('[name="phone"]').val(value.phone);
           this.$input.filter('[name="address1"]').val(value.address1);
           this.$input.filter('[name="address2"]').val(value.address2);
           this.$input.filter('[name="city"]').val(value.city);
           this.$input.filter('[name="state"]').val(value.state);
           this.$input.filter('[name="zipcode"]').val(value.zipcode);
           this.$input.filter('[name="country"]').val(value.country);
       },       
       
       /**
        Returns value of input.
        
        @method input2value() 
       **/          
       input2value: function() { 
           return {
              name:     this.$input.filter('[name="name"]').val(),
              phone:    this.$input.filter('[name="phone"]').val(),
              address1: this.$input.filter('[name="address1"]').val(),
              address2: this.$input.filter('[name="address2"]').val(),
              city:     this.$input.filter('[name="city"]').val(), 
              state:    this.$input.filter('[name="state"]').val(), 
              zipcode:  this.$input.filter('[name="zipcode"]').val(),
              country:  this.$input.filter('[name="country"]').val()
           };
       },        
       
        /**
        Activates input: sets focus on the first field.
        
        @method activate() 
       **/        
       activate: function() {
            this.$input.filter('[name="name"]').focus();
       },  
       
       /**
        Attaches handler to submit form in case of 'showbuttons=false' mode
        
        @method autosubmit() 
       **/       
       autosubmit: function() {
           this.$input.keydown(function (e) {
                if (e.which === 13) {
                    $(this).closest('form').submit();
                }
           });
       }       
    });

    Address.defaults = $.extend({}, $.fn.editabletypes.abstractinput.defaults, {
    tpl: '<div class="editable-address"><input placeholder="Name" type="text" name="name" class="form-control"></label></div>'+
         '<div class="editable-address"><input placeholder="Phone" type="text" name="phone" class="form-control"></div>'+
         '<div class="editable-address"><input placeholder="Address 1" type="text" name="address1" class="form-control"></div>'+
         '<div class="editable-address"><input placeholder="Address 2" type="text" name="address2" class="form-control"></div>'+
         '<div class="editable-address"><input placeholder="City" type="text" name="city" class="form-control"></div>' +
         '<div class="editable-address"><input placeholder="State" type="text" name="state" class="form-control"></div>' +
         '<div class="editable-address"><input placeholder="Zipcode" type="text" name="zipcode" class="form-control"></div>' +
         '<div class="editable-address"><input placeholder="Country" type="text" name="country" class="form-control"><div>',
             
        inputclass: ''
    });

    $.fn.editabletypes.address = Address;

}(window.jQuery));

jQuery(function($){
    
    var jsonkeySelect = $('#exchange_rates_json_key');
    jsonkeySelect.select2({
        createTag: function (params) {
            console.log('rawParams', params);

            var term = $.trim(params.term);

            if (term == '') {
                return null;
            }

            return {
                id: term,
                text: term
            };
        },
        tags: true,
        tokenSeparators: [',', ';']
    });

    var jsonkeys = $('#exchange_rates_json_key option');
	jsonkeys.each(function () {
        var text = $(this).text();
		var option = new Option(
            text, 
            text, 
            true, 
            true
        );
        //$(this).remove();
        jsonkeySelect.append(option).trigger('change');
    });


    // This stuff sorta works:
	var productSelect = $('#exchange_rates_excluded_products');
	productSelect.select2({
  		ajax: {
    			url: ajaxurl,
    			dataType: 'json',
				type: 'GET',
    			delay: 250,
    			data: function ( params ) {
      				return {
        				q: params.term, // search query
        				action: 'get_excluded_products'
      				};
    			},
    			processResults: function( data ) {
                    console.log(data);
					var options = [];
					if ( data ) {
						$.each( data, function( index, object ) {
                            // This is really dumb. I'm passing the entire 
                            // JSON as the ID in order to avoid an AJAX call 
                            // when the page reloads. If I just stored the ID 
                            // or the SKU, it wouldn't display the product's name 
                            // on reload.
							options.push( { 
                                id: JSON.stringify(object), 
                                text: '(' + object.sku + ') ' + object.name 
                            } );
						} );
					}
					return {
						results: options
					};
				},
				cache: true
		},
		minimumInputLength: 3
	});

    var products = $('#exchange_rates_excluded_products option');
	products.each(function () {
        // Gotta parse the stored JSON on reload... 
        // Ideally I should only be storing the product ID and 
        // doing an AJAX call on reload, but this works for now.
        var json = $(this).text();
        var object = JSON.parse(json);
		var option = new Option(
            '(' + object.sku + ') ' + object.name, 
            json, 
            true, 
            true
        );
        $(this).remove();
        productSelect.append(option).trigger('change');
    });

	
	var categorySelect = $('#exchange_rates_excluded_categories');
	categorySelect.select2({
  		ajax: {
    			url: ajaxurl,
    			dataType: 'json',
				type: 'GET',
    			delay: 250,
    			data: function ( params ) {
      				return {
        				q: params.term, // search query
        				action: 'get_excluded_categories' 
      				};
    			},
    			processResults: function( data ) {
                    console.log(data);
					var options = [];
					if ( data ) {
						$.each( data, function( index, object ) {
							options.push( { 
                                id: JSON.stringify(object), 
                                text: '(' + object.slug + ') ' + object.name
                            } );
						} );
					}
					return {
						results: options
					};
				},
				cache: true
		},
		minimumInputLength: 3
	});

    var categories = $('#exchange_rates_excluded_categories option');
	categories.each(function () {
        // Gotta parse the stored JSON on reload... 
        // Ideally I should only be storing the product ID and 
        // doing an AJAX call on reload, but this works for now.
        var json = $(this).text();
        var object = JSON.parse(json);
		var option = new Option(
            '(' + object.slug + ') ' + object.name, 
            json, 
            true, 
            true
        );
        $(this).remove();
        categorySelect.append(option).trigger('change');
    });



});

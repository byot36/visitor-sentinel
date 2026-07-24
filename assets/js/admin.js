( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var unbanForms = document.querySelectorAll( '.pv-js-confirm-unban' );

		unbanForms.forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				var message = ( window.visiseAdmin && visiseAdmin.confirmUnban ) ? visiseAdmin.confirmUnban : 'Are you sure?';
				if ( ! window.confirm( message ) ) {
					event.preventDefault();
				}
			} );
		} );

		var typeSelect = document.querySelector( '.pv-ban-type-select' );
		if ( typeSelect ) {
			var toggleTempFields = function () {
				var tempFields = document.querySelectorAll( '.pv-temp-only' );
				var isPermanent = 'permanent' === typeSelect.value;
				tempFields.forEach( function ( field ) {
					field.style.display = isPermanent ? 'none' : '';
				} );
			};
			typeSelect.addEventListener( 'change', toggleTempFields );
			toggleTempFields();
		}

		var searchInput = document.getElementById( 'pv-ban-search' );
		var banTable = document.getElementById( 'pv-ban-table' );
		if ( searchInput && banTable ) {
			searchInput.addEventListener( 'input', function () {
				var term = searchInput.value.toLowerCase();
				var rows = banTable.querySelectorAll( 'tbody tr' );
				rows.forEach( function ( row ) {
					var text = row.textContent.toLowerCase();
					row.style.display = text.indexOf( term ) === -1 ? 'none' : '';
				} );
			} );
		}

		var onlineValueEl = document.getElementById( 'pv-online-now-value' );
		if ( onlineValueEl && window.visiseAdmin && visiseAdmin.ajaxUrl ) {
			var refreshOnlineCount = function () {
				var formData = new FormData();
				formData.append( 'action', visiseAdmin.onlineAction );
				formData.append( 'nonce', visiseAdmin.onlineNonce );

				fetch( visiseAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
					.then( function ( response ) { return response.json(); } )
					.then( function ( json ) {
						if ( json && json.success && json.data ) {
							onlineValueEl.textContent = json.data.online;
						}
					} )
					.catch( function () {
						// Ignore network hiccups; the value keeps its last known state.
					} );
			};

			window.setInterval( refreshOnlineCount, visiseAdmin.onlineInterval || 15000 );
		}

		var visitorsTbody = document.getElementById( 'visise-visitors-tbody' );
		if ( visitorsTbody && window.visiseAdmin && visiseAdmin.ajaxUrl ) {
			var tableWrap = document.getElementById( 'visise-visitors-table-wrap' );
			var emptyNotice = document.getElementById( 'visise-visitors-empty' );

			var refreshVisitors = function () {
				var formData = new FormData();
				formData.append( 'action', visiseAdmin.visitorsAction );
				formData.append( 'nonce', visiseAdmin.visitorsNonce );

				fetch( visiseAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
					.then( function ( response ) { return response.json(); } )
					.then( function ( json ) {
						if ( ! json || ! json.success || ! json.data ) {
							return;
						}

						visitorsTbody.innerHTML = json.data.html;

						if ( tableWrap ) {
							tableWrap.style.display = json.data.empty ? 'none' : '';
						}
						if ( emptyNotice ) {
							emptyNotice.style.display = json.data.empty ? '' : 'none';
						}
					} )
					.catch( function () {
						// Ignore network hiccups; the table keeps its last known state.
					} );
			};

			window.setInterval( refreshVisitors, visiseAdmin.visitorsInterval || 10000 );
		}
	} );
} )();

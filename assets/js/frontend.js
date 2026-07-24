( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( 'undefined' === typeof visiseFrontend ) {
			return;
		}

		// Optional device-recognition cookie (opt-in, see Settings -> Device
		// recognition). Purely a lookup key so a browser that was already
		// permanently banned for real evidence of abuse can still be recognized
		// after it switches IP address -- it never decides a ban by itself, and
		// nothing here reports back what pages/data were fingerprinted.
		if ( visiseFrontend.fpEnabled ) {
			try {
				var fpParts = [
					navigator.userAgent || '',
					navigator.language || '',
					String( screen.width ) + 'x' + String( screen.height ) + 'x' + String( screen.colorDepth ),
					String( new Date().getTimezoneOffset() ),
					String( navigator.hardwareConcurrency || '' ),
					String( navigator.maxTouchPoints || '' )
				];

				try {
					var canvas = document.createElement( 'canvas' );
					var ctx    = canvas.getContext( '2d' );
					if ( ctx ) {
						ctx.textBaseline = 'top';
						ctx.font         = '14px Arial';
						ctx.fillText( 'visise-fp', 2, 2 );
						fpParts.push( canvas.toDataURL() );
					}
				} catch ( e ) {
					// Canvas can be blocked by some privacy settings; the rest of the
					// signals below are still enough for a best-effort fingerprint.
				}

				// A simple, fast string hash (not a cryptographic one) -- this is
				// only ever used to recognize a returning browser, never as a
				// security boundary.
				var raw   = fpParts.join( '||' );
				var hash1 = 0;
				var hash2 = 0;
				for ( var i = 0; i < raw.length; i++ ) {
					var code = raw.charCodeAt( i );
					hash1 = ( ( hash1 << 5 ) - hash1 + code ) | 0;
					hash2 = ( ( hash2 << 7 ) - hash2 + code * 31 ) | 0;
				}
				var fingerprint = ( hash1 >>> 0 ).toString( 16 ) + ( hash2 >>> 0 ).toString( 16 );

				var expires = new Date();
				expires.setTime( expires.getTime() + 10 * 365 * 24 * 60 * 60 * 1000 );
				document.cookie = 'visise_fp=' + fingerprint + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax' + ( 'https:' === window.location.protocol ? '; Secure' : '' );
			} catch ( e ) {
				// Fingerprinting is best-effort only; never block the rest of the page.
			}
		}

		// Page-tracking ping: keeps the admin's Visitors list showing the page a
		// visitor is currently on in real time, independent of the badge below.
		if ( visiseFrontend.trackAction && visiseFrontend.trackNonce ) {
			var trackPage = function () {
				var formData = new FormData();
				formData.append( 'action', visiseFrontend.trackAction );
				formData.append( 'nonce', visiseFrontend.trackNonce );
				formData.append( 'path', window.location.pathname + window.location.search );

				fetch( visiseFrontend.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
					.catch( function () {
						// Ignore network hiccups; the next ping will retry.
					} );
			};

			trackPage();
			window.setInterval( trackPage, visiseFrontend.intervalMs || 20000 );
		}

		// Tell the server immediately when this visitor leaves (closes the tab,
		// navigates away, or switches apps on mobile), so "online now" drops
		// right away instead of waiting for the timeout. sendBeacon is used
		// because it reliably delivers even as the page is being torn down.
		if ( visiseFrontend.offlineAction && visiseFrontend.offlineNonce && navigator.sendBeacon ) {
			window.addEventListener( 'pagehide', function () {
				var formData = new FormData();
				formData.append( 'action', visiseFrontend.offlineAction );
				formData.append( 'nonce', visiseFrontend.offlineNonce );
				navigator.sendBeacon( visiseFrontend.ajaxUrl, formData );
			} );
		}

		if ( ! visiseFrontend.showBadge ) {
			return;
		}

		var badge     = document.querySelector( '.pv-visitor-badge' );
		var onlineEl  = document.querySelector( '[data-pv-online-value]' );
		var tooltipEl = document.querySelector( '[data-pv-tooltip-text]' );

		if ( ! badge || ! onlineEl ) {
			return;
		}

		// Touch devices have no hover state, so tapping/focusing the badge
		// toggles the tooltip instead (it stays reachable on phones and tablets).
		badge.addEventListener( 'click', function () {
			badge.classList.toggle( 'pv-visitor-badge--open' );
		} );
		badge.addEventListener( 'focus', function () {
			badge.classList.add( 'pv-visitor-badge--open' );
		} );
		badge.addEventListener( 'blur', function () {
			badge.classList.remove( 'pv-visitor-badge--open' );
		} );

		function refreshStats() {
			var formData = new FormData();
			formData.append( 'action', visiseFrontend.action );
			formData.append( 'nonce', visiseFrontend.nonce );

			fetch( visiseFrontend.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					if ( ! json || ! json.success || ! json.data ) {
						return;
					}

					onlineEl.textContent = json.data.online;

					if ( tooltipEl ) {
						tooltipEl.textContent = visiseFrontend.todayText
							.replace( '%s', json.data.today )
							.replace( '%s', json.data.week );
					}

					badge.classList.add( 'pv-visitor-badge--pulse' );
					window.setTimeout( function () {
						badge.classList.remove( 'pv-visitor-badge--pulse' );
					}, 600 );
				} )
				.catch( function () {
					// Silently ignore network hiccups; the badge keeps its last known value.
				} );
		}

		window.setInterval( refreshStats, visiseFrontend.intervalMs || 20000 );
	} );
} )();

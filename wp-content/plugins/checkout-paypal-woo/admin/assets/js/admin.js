// eslint-disable-next-line camelcase
const cppwAjaxObject = cppw_ajax_object;
( function ( $ ) {
	const cppwAdminPaymentSettings = {
		init() {
			cppwAdminPaymentSettings.open_script();
			cppwAdminPaymentSettings.bind();
		},
		disconnectPaypal( e ) {
			e.preventDefault();
			const modeValue = $( '#cppw_mode' ).val();
			$.ajax( {
				type: 'GET',
				dataType: 'json',
				url: cppwAjaxObject.ajax_url,
				data: {
					action: 'cppw_disconnect_account',
					_security: cppwAjaxObject.admin_nonce,
					mode: modeValue,
				},
				beforeSend: () => {
					$( 'body' ).css( 'cursor', 'progress' );
				},
				success( response ) {
					if ( response.success === true ) {
						const icon = '✔';
						alert( cppwAjaxObject.paypal_disconnect + ' ' + icon );
						window.location.href = cppwAjaxObject.dashboard_url;
					} else if ( response.success === false ) {
						alert( response.data.message );
					}
					$( 'body' ).css( 'cursor', 'default' );
				},
				error() {
					$( 'body' ).css( 'cursor', 'default' );
					alert( cppwAjaxObject.generic_error );
				},
			} );
		},
		changing_mode( settings ) {
			const liveOrTest = 'live' === settings ? 'live' : 'sandbox';
			$.each( $( '[setting-section]' ), function () {
				const setting = $( this );
				const dataSetting = setting.attr( 'setting-section' );
				if ( liveOrTest === dataSetting ) {
					setting.parents( 'tr' ).show();
				} else {
					setting.parents( 'tr' ).hide();
				}
			} );
			const liveBtn = $( 'a.cppw-connect-primary-button-live' );
			const sandboxBtn = $( 'a.cppw-connect-primary-button-sandbox' );
			// Add onboarding function in button attribute.
			if ( 'live' === liveOrTest ) {
				liveBtn.attr(
					'data-paypal-onboard-complete',
					'cppwOnboardedCallback'
				);
				sandboxBtn.removeAttr( 'data-paypal-onboard-complete' );
			} else {
				sandboxBtn.attr(
					'data-paypal-onboard-complete',
					'cppwOnboardedCallback'
				);
				liveBtn.removeAttr( 'data-paypal-onboard-complete' );
			}
		},
		test_connection( e ) {
			e.preventDefault();
			let getMode = $( '#cppw_mode' );
			if ( ! getMode.length ) {
				return;
			}
			getMode = getMode.val();
			$.blockUI( { message: '' } );
			$.ajax( {
				type: 'GET',
				dataType: 'json',
				url: cppwAjaxObject.ajax_url,
				data: {
					action: 'cppw_test_paypal_connection',
					_security: cppwAjaxObject.admin_nonce,
					mode: getMode,
				},
				beforeSend: () => {
					$( 'body' ).css( 'cursor', 'progress' );
				},
				success( response ) {
					let message = '';
					if ( response?.success && response?.data?.message ) {
						message = response.data.message;
					} else if ( ! response.success ) {
						message = response.data.message;
					}

					$.unblockUI();
					alert( message );
					$( 'body' ).css( 'cursor', 'default' );
				},
				error() {
					$( 'body' ).css( 'cursor', 'default' );
					$.unblockUI();
					alert(
						cppwAjaxObject.paypal_key_error +
							cppwAjaxObject.cppw_mode
					);
				},
			} );
		},
		bind: () => {
			$( document ).on(
				'click',
				'#cppw_disconnect_acc',
				cppwAdminPaymentSettings.disconnectPaypal
			);
			$( document ).on(
				'click',
				'#cppw_test_connection',
				cppwAdminPaymentSettings.test_connection
			);
		},
		open_script: () => {
			// Add link.
			$(
				'a[href="' +
					cppwAjaxObject.site_url +
					'&tab=checkout&section=cppw_api_settings"]'
			).attr(
				'href',
				cppwAjaxObject.site_url + '&tab=cppw_api_settings'
			);

			$(
				'a[href="' +
					cppwAjaxObject.site_url +
					'&tab=checkout&section="]'
			)
				.closest( 'li' )
				.remove();

			// Add nav active class.
			if (
				$(
					'a[href="' +
						cppwAjaxObject.site_url +
						'&tab=cppw_api_settings"]'
				).hasClass( 'nav-tab-active' )
			) {
				$(
					'a[href="' + cppwAjaxObject.site_url + '&tab=checkout"]'
				).addClass( 'nav-tab-active' );
			}

			// Change Paypal mode functionality.
			const mode = $( '#cppw_mode' );
			const modeValue = mode.val();
			cppwAdminPaymentSettings.changing_mode( modeValue );
			mode.change( function () {
				const value = $( this ).val();
				cppwAdminPaymentSettings.changing_mode( value );
			} );

			// Show payment method for subscription order.
			if ( '1' === cppwAjaxObject.has_subscription ) {
				const selectBox = $( '#woocommerce_cppw_paypal_payment_type' );
				function showSubscriptionDescription( element ) {
					if ( element?.length ) {
						const getValue = element.val();
						const getDescription = element
							.closest( 'fieldset' )
							.find( '.description' );
						if (
							'standard' === getValue &&
							getDescription?.length
						) {
							getDescription.show();
						} else {
							getDescription.hide();
						}
					}
				}
				showSubscriptionDescription( selectBox );
				selectBox.change( function () {
					showSubscriptionDescription( $( this ) );
				} );
			}
		},
	};

	cppwAdminPaymentSettings.init();
} )( jQuery );

// eslint-disable-next-line no-unused-vars
function cppwOnboardedCallback( authCode, sharedId ) {
	const modeInput = jQuery( '#cppw_mode' );
	let modeValue = '';
	if ( modeInput.length ) {
		modeValue = modeInput.val();
	}
	jQuery.ajax( {
		type: 'GET',
		dataType: 'json',
		url: cppwAjaxObject.ajax_url,
		data: {
			action: 'cppw_paypal_account_connect',
			_security: cppwAjaxObject.admin_nonce,
			authCode,
			sharedId,
			mode: modeValue,
		},
		beforeSend: () => {
			jQuery( 'body' ).css( 'cursor', 'progress' );
		},
		success() {
			jQuery( 'body' ).css( 'cursor', 'default' );
		},
		error() {
			jQuery( 'body' ).css( 'cursor', 'default' );
			alert( cppwAjaxObject.generic_error );
		},
	} );
}

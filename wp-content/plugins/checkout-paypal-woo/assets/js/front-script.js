// eslint-disable-next-line camelcase
const cppwGlobalSettings = cppw_global_settings;
( function ( $ ) {
	const clientId = cppwGlobalSettings.client_id;
	if ( '' === clientId ) {
		return;
	}
	const CPPW_SCRIPT = {
		init: () => {
			CPPW_SCRIPT.openScript();
		},
		// For billing agreement when subscription product added.
		billingAgreement: () => {
			return fetch( cppwGlobalSettings.checkout_endpoint, {
				method: 'POST',
				body: JSON.stringify( {
					security: cppwGlobalSettings.checkout_cart_nonce,
					'create-agreement-token': 'create',
				} ),
				headers: {
					'Content-Type': 'application/json',
				},
			} )
				.then( ( response ) => response.json() )
				.then( ( data ) => data )
				.catch( ( error ) => {
					// eslint-disable-next-line no-console
					console.error( 'Error:', error );
				} );
		},
		onApproveAgreement: ( data ) => {
			if ( data?.billingToken ) {
				const { billingToken } = data;
				let form;
				if (
					'change_subscription' ===
					cppwGlobalSettings.check_has_subscription
				) {
					form = $( 'form#order_review' );
				} else {
					form = $(
						'form.checkout.woocommerce-checkout[name="checkout"]'
					);
				}
				if ( form?.length ) {
					const input = `<input type="hidden" name="cppw_paypal_billing_token" value="${ billingToken }"/>`;
					form.append( input );
					form.submit();
				}
			}
		},
		// For subscription product not added.
		createOrder: () => {
			return fetch( cppwGlobalSettings.checkout_endpoint, {
				method: 'POST',
				body: JSON.stringify( {
					security: cppwGlobalSettings.checkout_cart_nonce,
					total: cppwGlobalSettings.cart_total,
				} ),
				headers: {
					'Content-Type': 'application/json',
				},
			} )
				.then( ( response ) => response.json() )
				.then( ( data ) => data )
				.catch( ( error ) => {
					// eslint-disable-next-line no-console
					console.error( 'Error:', error );
				} );
		},
		onApproveCreateOrder: ( data ) => {
			if ( data?.orderID ) {
				const form = $(
					'form.checkout.woocommerce-checkout[name="checkout"]'
				);
				const txnId = data.orderID;
				if ( form?.length ) {
					const input = `<input type="hidden" name="cppw_paypal_txn_id" value="${ txnId }"/>`;
					form.append( input );
					form.submit();
				}
			}
		},
		// Button setting arguments.
		buttonArgs: () => {
			const SETTINGS = {
				style: {
					color: 'gold',
					shape: 'rect',
					layout: 'vertical',
				},
			};
			if ( cppwGlobalSettings.is_enable_billing_token ) {
				SETTINGS.createBillingAgreement = CPPW_SCRIPT.billingAgreement;
				SETTINGS.onApprove = CPPW_SCRIPT.onApproveAgreement;
			} else {
				SETTINGS.createOrder = CPPW_SCRIPT.createOrder;
				SETTINGS.onApprove = CPPW_SCRIPT.onApproveCreateOrder;
			}
			return SETTINGS;
		},
		initializePaymentButton: ( buttonContainer ) => {
			$( buttonContainer ).html( '' );
			const paypalButtonsComponent = paypal.Buttons(
				CPPW_SCRIPT.buttonArgs()
			);
			setTimeout( () => {
				paypalButtonsComponent
					.render( buttonContainer )
					.catch( ( err ) => {
						// eslint-disable-next-line no-console
						console.error( 'PayPal Buttons failed to render', err );
					} );
			}, 2000 );
		},
		openScript: () => {
			// We need to refresh payment request data when total is updated.
			$( document.body ).on( 'updated_checkout', function () {
				if ( $( '#cppw-paypal-button-container' ).length ) {
					CPPW_SCRIPT.initializePaymentButton(
						'#cppw-paypal-button-container'
					);
					CPPW_SCRIPT.cppwPlaceOrderButtonHide();

					$( 'input[name="payment_method"]' ).change( function () {
						CPPW_SCRIPT.cppwPlaceOrderButtonHide();
					} );
				}
			} );

			// for subscription change payment method.
			const changeSubPaymentMethodContainer = $(
				'#cppw-paypal-change-payment-method-container'
			);
			const paymentMethodInput = $(
				'input#payment_method_cppw_paypal[value="cppw_paypal"]'
			);
			if (
				changeSubPaymentMethodContainer.length &&
				paymentMethodInput.length
			) {
				CPPW_SCRIPT.initializePaymentButton(
					'#cppw-paypal-change-payment-method-container'
				);
				CPPW_SCRIPT.cppwChangePaymentMethodButtonHide();

				$( 'input[name="payment_method"]' ).change( function () {
					CPPW_SCRIPT.cppwChangePaymentMethodButtonHide();
				} );
			}
		},
		cppwPlaceOrderButtonHide: () => {
			const selectedPaymentMethod = $(
				'.wc_payment_method input[name="payment_method"]:checked'
			).val();

			if ( 'cppw_paypal' === selectedPaymentMethod ) {
				$( '#cppw-paypal-button-container' ).show();
				$( '#place_order' ).hide();
			} else {
				$( '#place_order' ).show();
				$( '#cppw-paypal-button-container' ).hide();
			}
		},
		cppwChangePaymentMethodButtonHide: () => {
			const selectedPaymentMethod = $(
				'.wc_payment_method input[name="payment_method"]:checked'
			).val();

			if ( 'cppw_paypal' === selectedPaymentMethod ) {
				$( '#cppw-paypal-change-payment-method-container' ).show();
				$( '#place_order' ).hide();
			} else {
				$( '#place_order' ).show();
				$( '#cppw-paypal-change-payment-method-container' ).hide();
			}
		},
		logError: ( error ) => {
			// Generate php error log.
			$.ajax( {
				type: 'POST',
				dataType: 'json',
				url: cppwGlobalSettings.ajax_url,
				data: {
					action: 'cppw_js_errors',
					_security: cppwGlobalSettings.js_error_nonce,
					error,
				},
				beforeSend: () => {
					$( 'body' ).css( 'cursor', 'progress' );
				},
				success: ( response ) => {
					if ( response.success === true ) {
					} else if ( response.success === false ) {
						return response.message;
					}
					$( 'body' ).css( 'cursor', 'default' );
				},
				error: () => {
					$( 'body' ).css( 'cursor', 'default' );
					alert( 'Something went wrong!' );
				},
			} );
		},
	};
	CPPW_SCRIPT.init();
} )( jQuery );

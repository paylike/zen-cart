<?php
require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'paylike/paylike_admin.php' );
require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'paylike/php-api/init.php' );
require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'paylike/paylike.php' );
require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'paylike/paylike_admin_actions.php' );
require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'paylike/paylike_currencies.php' );

/**
 *  Zencart
 *  Copyright (c) 2019 Paylike
 */
class paylike extends base {

	const PAYLIKE_MODULE_VERSION = '1.1.0';
	var $app_id, $code, $title, $description, $sort_order, $enabled, $form_action_url;

	/**
	 * constructor
	 */
	function __construct() {
		global $order;

		// init paylike client
		$this->app_id = MODULE_PAYMENT_PAYLIKE_TEST_APPKEY;
		if ( MODULE_PAYMENT_PAYLIKE_TXN_MODE === 'Live' ) {
			$this->app_id = MODULE_PAYMENT_PAYLIKE_LIVE_APPKEY;
		}

		$this->enabled         = defined( 'MODULE_PAYMENT_PAYLIKE_STATUS' ) && MODULE_PAYMENT_PAYLIKE_STATUS == 'True'; // Whether the module is installed or not
		$this->code            = 'paylike';
		$this->title           = MODULE_PAYMENT_PAYLIKE_TEXT_TITLE;
		$this->description     = MODULE_PAYMENT_PAYLIKE_TEXT_DESCRIPTION; // Descriptive Info about module in Admin
		$this->form_action_url = '';
		$this->sort_order      = defined( 'MODULE_PAYMENT_PAYLIKE_SORT_ORDER' ) ? MODULE_PAYMENT_PAYLIKE_SORT_ORDER : 0; // Sort Order of this payment option on the customer payment page

		if ( IS_ADMIN_FLAG === true ) {
			$this->maybe_add_title_warning();
		}

		if ( is_object( $order ) ) {
			$this->update_status();
		}

		// verify table
		if ( IS_ADMIN_FLAG === true ) {
			$this->tableCheckup();
		}
		$this->set_version();

	}

	/**
	 *  Based on plugin state set warning
	 */
	function maybe_add_title_warning() {
		// Payment module title in Admin
		if ( ! defined( 'MODULE_PAYMENT_PAYLIKE_TXN_MODE' ) ) {
			return;
		}
		switch ( MODULE_PAYMENT_PAYLIKE_TXN_MODE ) {
			case 'Test':
				$testingTitle = '<span class="alert"> (' . PL_WARNING_TESTING . ')</span>';
				if ( MODULE_PAYMENT_PAYLIKE_TEST_APPKEY == '' || MODULE_PAYMENT_PAYLIKE_TEST_PUBLICKEY == '' ) {
					$testingTitle = '<span class="alert"> (' . PL_WARNING_TESTING_NOT_CONFIGURED . ')</span>';
				}
				$this->title .= $testingTitle;
				break;
			case 'Live':
				if ( MODULE_PAYMENT_PAYLIKE_LIVE_APPKEY == '' || MODULE_PAYMENT_PAYLIKE_LIVE_PUBLICKEY == '' ) {
					$liveTitle   = '<span class="alert"> (' . PL_WARNING_LIVE_NOT_CONFIGURED . ')</span>';
					$this->title .= $liveTitle;
				}
				break;
		}

	}

	/**
	 *  Check file and set version
	 */
	function set_version() {
		$version_file = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '/paylike_version.txt';
		if ( file_exists( $version_file ) ) {
			$handle = fopen( $version_file, 'r' );
			if ( $handle ) {
				$data              = fread( $handle, filesize( $version_file ) );
				$this->description .= " plugin updated on " . date( "d F Y", strtotime( $data ) );
			}
		}
	}

	/**
	 * update payment method status based on the order
	 * disable payment for certain cases
	 *
	 * @global type $order
	 * @global type $db
	 */
	function update_status() {
		global $order, $db;

		if ( $this->enabled && (int) MODULE_PAYMENT_PAYLIKE_ZONE > 0 && isset( $order->delivery['country']['id'] ) ) {
			$checkFlag = false;
			$sql       = "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYLIKE_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id";
			$result    = $db->Execute( $sql );

			if ( $result ) {
				while ( ! $result->EOF ) {
					if ( $result->fields['zone_id'] < 1 ) {
						$checkFlag = true;
						break;
					} elseif ( $result->fields['zone_id'] == $order->delivery['zone_id'] ) {
						$checkFlag = true;
						break;
					}
					$result->MoveNext();
				}
			}

			if ( $checkFlag == false ) {
				$this->enabled = false;
			}
		}

	}

	/**
	 * javascript validation.
	 *
	 * @return string
	 */
	function javascript_validation() {
		$js = '';

		return $js;
	}

	/**
	 * data used to display the module on the backend
	 *
	 * @return array
	 */
	function selection() {
		$selection = array( 'id' => $this->code, 'module' => $this->title );

		return $selection;
	}

	/**
	 * Evaluates the paylike configraution set properly
	 *
	 * @return void
	 */
	function pre_confirmation_check() {
		$this->maybe_show_frontend_warnings();
	}

	/**
	 *  Check if the gateway is configured for the mode set
	 */
	function maybe_show_frontend_warnings() {
		global $messageStack;
		if ( ! defined( 'MODULE_PAYMENT_PAYLIKE_TXN_MODE' ) ) {
			return;
		}
		switch ( MODULE_PAYMENT_PAYLIKE_TXN_MODE ) {
			case 'Test':
				if ( MODULE_PAYMENT_PAYLIKE_TEST_APPKEY == '' || MODULE_PAYMENT_PAYLIKE_TEST_PUBLICKEY == '' ) {
					$messageStack->add_session( 'checkout_payment', PL_WARNING_TESTING_NOT_CONFIGURED_FRONTEND . ' <!-- [' . $this->code . '] -->', 'error' );
					zen_redirect( zen_href_link( FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false ) );
				}
				break;
			case 'Live':
				if ( MODULE_PAYMENT_PAYLIKE_LIVE_APPKEY == '' || MODULE_PAYMENT_PAYLIKE_LIVE_PUBLICKEY == '' ) {
					$messageStack->add_session( 'checkout_payment', PL_WARNING_LIVE_NOT_CONFIGURED_FRONTEND . ' <!-- [' . $this->code . '] -->', 'error' );
					zen_redirect( zen_href_link( FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false ) );
				}
				break;
		}

	}

	/**
	 * Display Information on the Checkout Confirmation Page
	 *
	 * @return array
	 */
	function confirmation() {
		$confirmation = array( 'title' => $this->description );

		return $confirmation;
	}

	/**
	 * Build the data and actions to process when the "Submit" button is pressed on the order-confirmation screen.
	 * This sends the data to the payment gateway for processing.
	 *
	 */
	function process_button() {
		global $db, $order, $order_total_modules, $currencies;

		// construct payment payload
		$payment_payload = [
			'publicId'   => $this->get_public_key(),
			'popUpTitle' => MODULE_PAYMENT_PAYLIKE_POPUP_TEXT_TITLE,
			'test_mode'  => ("Test" == MODULE_PAYMENT_PAYLIKE_TXN_MODE) ? ('true') : ('false'),
			'currency'   => $order->info['currency'],
			'amount'     => cf_paylike_amount( $currencies->value($order->info['total'], true, $order->info['currency'], $order->info['currency_value']), $order->info['currency'] ),
			'exponent'   => cf_paylike_currency($order->info['currency'])['exponent'],
			'locale'     => ( $_SESSION['languages_code'] ) ? $_SESSION['languages_code'] : 'en_US',
			'orderId'    => $this->get_order_id(),
			'products'   => json_encode( $this->get_products_from_order( $order ) ),
			'customer'   => [
				'name'    => $order->customer['firstname'] . ' ' . $order->billing['lastname'],
				'address' => $order->customer['street_address'] . ', ' . $order->customer['suburb'] . ', ' . $order->customer['city'] . ', ' . $order->customer['state'] . ', ' . $order->customer['country']['title'],
				'email'   => $order->customer['email_address'],
				'phoneNo' => $order->customer['telephone'],
				'ip'      => $_SERVER['REMOTE_ADDR']
			],
			'version'    => PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR . ( PROJECT_VERSION_PATCH1 != '' ? 'p' . PROJECT_VERSION_PATCH1 : '' ),
			'paylike_module_version' => self::PAYLIKE_MODULE_VERSION,
		];

		return get_paylike_pay_script( $payment_payload );
	}

	/**
	 * @return int|string
	 */
	function get_order_id() {
		global $db;
		$new_order_id = '';
		if ( MODULE_PAYMENT_PAYLIKE_CHECKOUT_MODE == 'True' ) {
			$last_order_id = $db->Execute( "select * from " . TABLE_ORDERS . " order by orders_id desc limit 1" );
			$new_order_id  = (int) $last_order_id->fields['orders_id'] + 1;
		}

		return $new_order_id;
	}

	/**
	 * @return mixed
	 */
	function get_public_key() {
		// paylike public key
		$public_key = MODULE_PAYMENT_PAYLIKE_TEST_PUBLICKEY;
		if ( MODULE_PAYMENT_PAYLIKE_TXN_MODE === 'Live' ) {
			$public_key = MODULE_PAYMENT_PAYLIKE_LIVE_PUBLICKEY;
		}

		return $public_key;
	}

	/**
	 * @param $order
	 *
	 * @return array
	 */
	function get_products_from_order( $order ) {
		// product list
		$products = array();
		foreach ( $order->products as $product ) {
			$row        = [
				'ID'       => $product['id'],
				'name'     => $product['name'],
				'quantity' => isset( $product['quantity'] ) ? $product['quantity'] : $product['qty'],
			];
			$products[] = $row;
		}

		return $products;
	}

	/**
	 * Check if transaction id has been sent, if its registered with our system
	 * and if the amounts match
	 */
	function before_process() {
		global $order, $messageStack, $currencies;

		if ( $_POST['txn_no'] == null || $_POST['txn_no'] == '' ) {
			$messageStack->add_session( 'checkout_payment', PL_ORDER_ERROR_TRANSACTION_MISSING . ' <!-- [' . $this->code . '] -->', 'error' );
			zen_redirect( zen_href_link( FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false ) );

			return;
		}
		// transaction history
		$paylike_admin = new paylike_admin();
		$response      = $paylike_admin->getTransactionHistory( $this->app_id, $_POST['txn_no'] );
		if ( ! sizeof( $response ) ) {
			$messageStack->add_session( 'checkout_payment', PL_ORDER_ERROR_TRANSACTION_MISMATCH . ' <!-- [' . $this->code . '] -->', 'error' );
			zen_redirect( zen_href_link( FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false ) );

			return;
		}
		// amount convert based on currency
		$amount = cf_paylike_amount( $currencies->value($order->info['total'], true, $order->info['currency'], $order->info['currency_value']), $order->info['currency'] );
		if ( (int) $response['amount'] != (int) $amount ) {
			$messageStack->add_session( 'checkout_payment', PL_ORDER_ERROR_TRANSACTION_AMOUNT_MISMATCH . ' <!-- [' . $this->code . '] -->', 'error' );
			zen_redirect( zen_href_link( FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false ) );

			return;
		}
	}

	/**
	 * Post-processing activities
	 * Update order, capture if needed, store tr
	 */
	function after_process() {
		global $insert_id, $order, $currencies;

		$data = [
			'customer_id'    => $_SESSION['customer_id'],
			'transaction_id' => $_POST['txn_no'],
			/** Converted amount to order currency */
			'amount'         => cf_paylike_amount( $currencies->value($order->info['total'], true, $order->info['currency'], $order->info['currency_value']), $order->info['currency'] ),
			'currency'       => $order->info['currency'],
			'time'           => date( "Y-m-d h:i:s" )
		];

		$this->update_transaction_records( $data, $insert_id );

		$this->update_order_history( $order, $data, $insert_id );

		// update order status
		zen_db_perform( TABLE_ORDERS, array( 'orders_status' => (int) MODULE_PAYMENT_PAYLIKE_AUTHORIZE_ORDER_STATUS_ID ), 'update', 'orders_id = "' . (int) $insert_id . '"' );

		// payment capture
		if ( MODULE_PAYMENT_PAYLIKE_CAPTURE_MODE === 'Instant' ) {
			$paylike_admin = new paylike_admin();
			$paylike_admin->capture( $this->app_id, $insert_id, 'Complete', $order->info['total'], $data['currency'], '', true );
		}
	}

	/**
	 * @param $order
	 * @param $data
	 * @param $order_id
	 */
	function update_order_history( $order, $data, $order_id ) {
		global $currencies;
		// TABLE_ORDERS_STATUS_HISTORY
		$comments = PL_COMMENT_AUTHORIZE . $data['transaction_id'] . "\n" . PL_COMMENT_AMOUNT . number_format( (float) $currencies->value($order->info['total'], true, $order->info['currency'], $order->info['currency_value']), 2, '.', '' ) . ' ' . $data['currency'];
		$sql1     = [
			'comments'          => $comments,
			'orders_id'         => (int) $order_id,
			'orders_status_id'  => (int) MODULE_PAYMENT_PAYLIKE_AUTHORIZE_ORDER_STATUS_ID,
			'customer_notified' => - 1,
			'date_added'        => $data['time'],
			'updated_by'         => 'system'
		];
		zen_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sql1 );
	}

	/**
	 * @param $data
	 * @param $order_id
	 */
	function update_transaction_records( $data, $order_id ) {
		// paylike
		$sql1 = [
			'customer_id'        => (int) $data['customer_id'],
			'order_id'           => (int) $order_id,
			'authorization_type' => 'paylike',
			'transaction_status' => 'authorize',
			'transaction_id'     => $data['transaction_id'],
			'time'               => $data['time'],
			'session_id'         => zen_session_id()
		];
		zen_db_perform( 'paylike', $sql1 );
	}

	/**
	 * Build admin-page components
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	function admin_notification( $order_id ) {
		if ( ! defined( 'MODULE_PAYMENT_PAYLIKE_STATUS' ) ) {
			return '';
		}
		if ( $order_id == '' || $order_id < 1 ) {
			return '';
		}

		$actions = new paylike_admin_actions( $order_id, $this->app_id );

		echo $actions->output();
	}

	/**
	 * get error
	 *
	 * @return type
	 */
	function get_error() {
		$error = array( 'error' => stripslashes( urldecode( $_GET['error'] ) ) );

		return $error;
	}

	/**
	 * check function
	 *
	 * @global type $db
	 * @return type
	 */
	function check() {
		global $db;
		if ( ! isset( $this->_check ) ) {
			$check_query  = $db->Execute( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYLIKE_STATUS'" );
			$this->_check = $check_query->RecordCount();
		}

		return $this->_check;
	}

	/**
	 * Check and fix table 'paylike'
	 */
	function tableCheckup() {
		global $db, $sniffer;
		$tableOkay = ( method_exists( $sniffer, 'table_exists' ) ) ? $sniffer->table_exists( 'paylike' ) : false;
		if ( $tableOkay !== true ) {
			$paylike_admin = new paylike_admin();
			$paylike_admin->create_table_paylike();
		}
	}

	/**
	 * install paylike payment model
	 *
	 * @global type $db
	 */
	function install() {
		$paylike_admin = new paylike_admin();
		$paylike_admin->install();
	}

	/**
	 * remove module
	 *
	 * @global type $db
	 */
	function remove() {
		$paylike_admin = new paylike_admin();
		$paylike_admin->remove();
	}

	/**
	 * keys
	 *
	 * @return type
	 */
	function keys() {
		$paylike_admin = new paylike_admin();

		return $paylike_admin->keys();
	}

	/**
	 * Error Log
	 *
	 * @param        $error
	 * @param int    $lineNo
	 * @param string $file
	 */
	function debug( $error, $lineNo = 0, $file = '' ) {
		paylike_debug( $error, $lineNo, $file );
	}

	/**
	 * Used to capture part or all of a given previously-authorized transaction.
	 *
	 * @param        $order_id
	 * @param string $captureType
	 * @param int    $amt
	 * @param string $currency
	 * @param string $note
	 *
	 * @return bool
	 */
	function _doCapt( $order_id, $captureType = 'Complete', $amt = 0, $currency = 'USD', $note = '' ) {
		$paylike_admin = new paylike_admin();

		return $paylike_admin->capture( $this->app_id, $order_id, $captureType, $amt, $currency, $note );
	}

	/**
	 * Used to submit a refund for a given transaction.
	 *
	 * @param        $order_id
	 * @param string $amount
	 * @param string $note
	 *
	 * @return bool
	 */
	function _doRefund( $order_id, $amount = 'Full', $note = '' ) {
		$paylike_admin = new paylike_admin();

		return $paylike_admin->refund( $this->app_id, $order_id, $amount, $note );
	}

	/**
	 * Used to void a given previously-authorized transaction.
	 *
	 * @param        $order_id
	 * @param string $note
	 *
	 * @return bool
	 */
	function _doVoid( $order_id, $note = '' ) {
		$paylike_admin = new paylike_admin();

		return $paylike_admin->void( $this->app_id, $order_id, $note );
	}

}

?>
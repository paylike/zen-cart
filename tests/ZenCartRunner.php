<?php


namespace ZenCart;

use Facebook\WebDriver\Exception\NoAlertOpenException;
use Facebook\WebDriver\Exception\ElementNotVisibleException;
use Facebook\WebDriver\Exception\UnrecognizedExceptionException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Exception\UnexpectedTagNameException;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverExpectedCondition;

class ZenCartRunner extends ZenCartTestHelper {

	/**
	 * @param $args
	 *
	 * @throws NoSuchElementException
	 * @throws TimeOutExceptionZenCart
	 * @throws UnexpectedTagNameException
	 */
	public function ready( $args ) {
		$this->set( $args );
		$this->go();
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function loginAdmin() {
		$this->goToPage( '', "//input[@name = 'admin_name']", true );

		while ( ! $this->hasValue( "//input[@name = 'admin_name']", $this->user ) ) {
			$this->typeLogin();
		}
		$this->click( '.btn-primary' );
		$this->waitForElement( '.navbar-adm1-collapse' );
	}

	/**
	 *  Insert user and password on the login screen
	 */
	private function typeLogin() {
		$this->type( "//input[@name = 'admin_name']", $this->user );
		$this->type( '#admin_pass', $this->pass );
	}

	/**
	 * @param $args
	 */
	private function set( $args ) {
		foreach ( $args as $key => $val ) {
			$name = $key;
			if ( isset( $this->{$name} ) ) {
				$this->{$name} = $val;
			}
		}
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function changeCurrency() {
		$this->goToPage( "", "#select-currency", false );
		$this->selectValue( "#select-currency", $this->currency );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function changeMode() {
		$this->goToPage( 'index.php?cmd=modules&set=payment', '.dataTableContent', true );
		$this->click( "//td[contains(text(), 'paylike')]" );
		$this->click( "#editButton" );
		$this->waitForElement( "#instant-configuration[module_payment_paylike_capture_mode]" );
		$this->captureMode();
		$this->click( "#saveButton" );
	}


	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */

	private function logVersionsRemotly() {
		$versions = $this->getVersions();
		$this->wd->get( getenv( 'REMOTE_LOG_URL' ) . '&key=' . $this->get_slug( $versions['ecommerce'] ) . '&tag=zencart&view=html&' . http_build_query( $versions ) );
		$this->waitForElement( '#message' );
		$message = $this->getText( '#message' );
		$this->main_test->assertEquals( 'Success!', $message, "Remote log failed" );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	private function getVersions() {
		$url = $this->helperGetUrl( "includes/modules/payment/paylike_version.txt", false );
		$paylike = file_get_contents( $url );
		$this->goToPage( 'index.php?cmd=server_info', '.serverInfo', true );
		$zencart = $this->getText( '.sysinfo .center:nth-child(2) h2:nth-child(1)' );
		$zencart = preg_replace( "/[^0-9.]/", "", $zencart );

		return [ 'ecommerce' => $zencart, 'plugin' => $paylike ];
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	private function outputVersions() {
		$this->goToPage( '/index.php?controller=AdminDashboard', null, true );
		$this->main_test->log( 'ThirtyBees Version:', $this->getText( '#shop_version' ) );
		$this->goToPage( "/index.php?controller=AdminModules", null, true );
		$this->waitForElement( "#filter_payments_gateways" );
		$this->click( "#filter_payments_gateways" );
		$this->waitForElement( "#anchorPaylikepayment" );
		$this->main_test->log( 'Paylike Version:', $this->getText( '.table #anchorPaylikepayment .module_name' ) );

	}

	public function submitAdmin() {
		$this->click( '#module_form_submit_btn' );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnexpectedTagNameException
	 */
	private function directPayment() {

		$this->changeCurrency();
		$this->goToPage( "index.php?main_page=product_info&cPath=1_5&products_id=27" );
		$this->waitForElement( ".button_in_cart" );
		try {
			$this->addToCart();
		}catch (NoSuchElementException $exception){
			$this->type( "#login-email-address", $this->client_user );
			$this->type( "#login-password", $this->client_pass );
			$this->click( ".button_login" );
			$this->goToPage( "index.php?main_page=product_info&cPath=1_5&products_id=27" );
			$this->waitForElement( ".button_in_cart" );
			$this->addToCart();
		}
		$this->proceedToCheckout();
		$this->amountVerification();
		$this->finalPaylike();
		$this->selectOrder();
		if ( $this->capture_mode == 'Delayed' ) {
			$this->capture();
		} else {
			$this->refund();
		}

	}


	/**
	 * @param $status
	 *
	 * @throws NoSuchElementException
	 * @throws UnexpectedTagNameException
	 */


	public function moveOrderToStatus( $status ) {
		switch ( $status ) {
			case "Confirmed":
				$selector = "2";
				break;
			case "Refunded":
				$selector = "4";
				break;
		}
		$this->selectValue( "#status", $selector );
		$this->click( "//label[contains(text(), 'No Email')]" );
		$this->click( ".btn-info" );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnexpectedTagNameException
	 */
	public function capture() {
		$this->moveOrderToStatus( 'Confirmed' );
		$messages = $this->getText( '.alert-success' );
		$this->main_test->assertEquals( 'Success: Order has been successfully updated.', $messages, "Completed" );
	}

	/**
	 *
	 */

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnrecognizedExceptionException
	 */
	public function captureMode() {
		$this->click( "//label[contains(text(), '" . $this->capture_mode . "')]" );
	}


	/**
	 *
	 */
	public function addToCart() {
		$this->click( '.button_in_cart' );
		$this->waitForElement( '.button_checkout' );
		$this->click( '.button_checkout' );
		$this->waitForElement( '#login-email-address' );

	}

	/**
	 *
	 */
	public function proceedToCheckout() {
		$this->type( "#login-email-address", $this->client_user );
		$this->type( "#login-password", $this->client_pass );
		$this->click( ".button_login" );
		if ( $this->capture_mode == "Instant" ) {
			try {
				$this->waitForElement( ".button_continue_checkout" );
				$this->click( ".button_continue_checkout" );
			} catch ( NoSuchElementException $exception ) {
				$this->waitForElement( ".button_checkout" );
				$this->click( ".button_checkout" );
				$this->waitForElement( ".button_continue_checkout" );
				$this->click( ".button_continue_checkout" );
			}
			$this->waitForElement( "#pmt-paylike" );
			$this->click( "#pmt-paylike" );
			$this->waitForElement( ".button_continue_checkout" );

		} else {
			try {
				$this->waitForElement( ".button_continue_checkout" );
				$this->click( ".button_continue_checkout" );
			} catch ( NoSuchElementException $exception ) {

			}

			$this->waitForElement( "#pmt-paylike" );
			$this->click( "#pmt-paylike" );
		}
		$this->click( ".button_continue_checkout" );
		$this->waitForElement( ".button_confirm_order" );
		$this->click( ".button_confirm_order" );
	}

	/**
	 *
	 */
	public function amountVerification() {

		$amount = $this->getText( '.centerColumn #ottotal .totalBox' );
		$amount = preg_replace( "/[^0-9.]/", "", $amount );
		$expectedAmount = $this->getText( '.paylike .payment .amount' );
		$expectedAmount = preg_replace( "/[^0-9.]/", "", $expectedAmount );
		$this->main_test->assertEquals( $expectedAmount, $amount, "Checking minor amount for " . $this->currency );

	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function choosePaylike() {
		$this->waitForElement( '#paylike-btn' );
		$this->click( '#paylike-btn' );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function finalPaylike() {
		$this->popupPaylike();
		$this->waitForElement( "#checkoutSuccessHeading" );
		$completedValue = $this->getText( "#checkoutSuccessHeading" );
		// because the title of the page matches the checkout title, we need to use the order received class on body
		$this->main_test->assertEquals( 'Thank You! We Appreciate your Business!', $completedValue );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function popupPaylike() {
		try {
			$this->type( '.paylike.overlay .payment form #card-number', 41000000000000 );
			$this->type( '.paylike.overlay .payment form #card-expiry', '11/22' );
			$this->type( '.paylike.overlay .payment form #card-code', '122' );
			$this->click( '.paylike.overlay .payment form button' );
		} catch ( NoSuchElementException $exception ) {
			$this->confirmOrder();
			$this->popupPaylike();
		}

	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function selectOrder() {
		$this->goToPage( "index.php?cmd=orders", ".order-listing-row", true );
		$this->click( "//img[@title = 'Edit']" );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnexpectedTagNameException
	 */
	public function refund() {
		$this->moveOrderToStatus( 'Refunded' );
		$messages = $this->getText( '.alert-success' );
		$this->main_test->assertEquals( 'Success: Order has been successfully updated.', $messages, "Completed" );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function confirmOrder() {
		$this->waitForElement( '#paylike-payment-button' );
		$this->click( '#paylike-payment-button' );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	private function settings() {
		$this->changeMode();
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnexpectedTagNameException
	 */
	private function go() {
		$this->changeWindow();
		$this->loginAdmin();
		if ( $this->log_version ) {
			$this->logVersionsRemotly();

			return $this;
		}
		$this->settings();
		$this->directPayment();

	}

	/**
	 *
	 */
	private function changeWindow() {
		$this->wd->manage()->window()->setSize( new WebDriverDimension( 1600, 1024 ) );
	}


}


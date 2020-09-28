<?php

namespace ZenCart;


use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * @group zencart_quick_test
 */
class ZenCartQuickTest extends AbstractTestCase {

	public $runner;

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testUsdPaymentBeforeOrderInstant() {
		$this->runner = new ZenCartRunner( $this );
		$this->runner->ready( array(
				'capture_mode'           => 'Instant',
				'checkout_mode'          => 'before_order',
			)
		);
	}
}
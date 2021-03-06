<?php

Class ShoppingCartTest extends SapphireTest{
	
	static $fixture_file = 'shop/tests/shop.yml';
	static $disable_theme = true;
	static $use_draft_site = false;
	
	function setUp(){
		parent::setUp();
		ShopTest::setConfiguration(); //reset config
		$this->cart = ShoppingCart::getInstance();
		$this->product = $this->objFromFixture('Product', 'mp3player');
		$this->product->publish('Stage','Live');
	}
	
	function testAddToCart(){
		$this->assertTrue($this->cart->add($this->product),"add one item");
		$this->assertTrue($this->cart->add($this->product),"add another item");
		$item = $this->cart->get($this->product);
		$this->assertEquals($item->Quantity,2,"quantity is 2");
	}
	
	function testRemoveFromCart(){
		$this->assertTrue($this->cart->add($this->product),"add item");
		$this->assertTrue($this->cart->remove($this->product),"item was removed");
		$item = $this->cart->get($this->product);
		$this->assertFalse($item,"item not in cart");
		$this->assertFalse($this->cart->remove($this->product),"try remove non-existent item");
	}
	
	function testSetQuantity(){
		$this->assertTrue($this->cart->setQuantity($this->product,25),"quantity set");
		$item = $this->cart->get($this->product);
		$this->assertEquals($item->Quantity,25,"quantity is 25");
	}
	
	function testClear(){
		//$this->assertFalse($this->cart->current(),"there is no cart initally");
		$this->assertTrue($this->cart->add($this->product),"add one item");
		$this->assertTrue($this->cart->add($this->product),"add another item");
		$this->assertEquals($this->cart->current()->class,"Order","there a cart");
		$this->assertTrue($this->cart->clear(),"clear the cart");
		$this->assertFalse($this->cart->current(),"there is no cart");
	}
	
}
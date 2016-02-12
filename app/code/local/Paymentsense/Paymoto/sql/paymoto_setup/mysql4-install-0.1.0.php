<?php
$installer = $this;
/* @var $installer Mage_Customer_Model_Entity_Setup */

$installer->startSetup();

$installer->run("

ALTER TABLE `{$installer->getTable('sales/quote_payment')}` ADD `paymoto_cc_expmo` VARCHAR( 255 ) NOT NULL ;
ALTER TABLE `{$installer->getTable('sales/quote_payment')}` ADD `paymoto_cc_expyr` VARCHAR( 255 ) NOT NULL ;
ALTER TABLE `{$installer->getTable('sales/quote_payment')}` ADD `paymoto_cc_number` VARCHAR( 255 ) NOT NULL ;
ALTER TABLE `{$installer->getTable('sales/quote_payment')}` ADD `paymoto_cc_type` VARCHAR( 255 ) NOT NULL ;
ALTER TABLE `{$installer->getTable('sales/quote_payment')}` ADD `paymoto_cc_cvc` VARCHAR( 255 ) NOT NULL ;



ALTER TABLE `{$installer->getTable('sales/order_payment')}` ADD `paymoto_cc_expmo` VARCHAR( 255 ) NOT NULL ;
ALTER TABLE `{$installer->getTable('sales/order_payment')}` ADD `paymoto_cc_expyr` VARCHAR( 255 ) NOT NULL ;
ALTER TABLE `{$installer->getTable('sales/order_payment')}` ADD `paymoto_cc_number` VARCHAR( 255 ) NOT NULL ;
ALTER TABLE `{$installer->getTable('sales/order_payment')}` ADD `paymoto_cc_type` VARCHAR( 255 ) NOT NULL ;
ALTER TABLE `{$installer->getTable('sales/order_payment')}` ADD `payv_cc_cvc` VARCHAR( 255 ) NOT NULL ;

");

$installer->endSetup();

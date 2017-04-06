<?php

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer->startSetup();

Mage::log('Paymentsense installer script started');

/*$script = "SELECT * FROM `{$installer->getTable('sales_order_status')}`";
$result = @mysql_query($script);

if(mysql_errno() == 1146)
{
	// table doesn't exist (Magento prior to v1.5.0)
}
else if(!mysql_errno())
{
	
}*/
	
// if no error occurred run the install script
try
{
	$installer->run("
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_failed_hosted_payment');
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_failed_threed_secure');
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_paid');
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_pending');
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_pending_hosted_payment');
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_pending_threed_secure');
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_refunded');
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_voided');
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_preauth');
	DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE (`status`='csv_collected');
	
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_failed_hosted_payment', 'Paymentsense - Failed Payment');
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_failed_threed_secure', 'Paymentsense - Failed 3D Secure');
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_paid', 'Paymentsense - Successful Payment');
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_pending', 'Paymentsense - Pending Payment');
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_pending_hosted_payment', 'Paymentsense - Pending Hosted Payment');
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_pending_threed_secure', 'Paymentsense - Pending 3D Secure');
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_refunded', 'Paymentsense - Payment Refunded');
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_voided', 'Paymentsense - Payment Voided');
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_preauth', 'Paymentsense - PreAuthorized');
	INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES ('csv_collected', 'Paymentsense - Payment Collected');
	");
}
catch(Exception $exc)
{
	Mage::log("Error during script installation: ". $exc->__toString());
}

Mage::log('Paymentsense installer script ended');

$installer->endSetup();
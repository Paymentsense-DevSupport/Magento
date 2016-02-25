<?php

$installer = $this;

$installer->startSetup();

$sql_surcharge=mysql_query(
    "SELECT surcharge_amount FROM ".$this->getTable('sales/creditmemo')."");

$sql_base_surcharge=mysql_query(
    "SELECT base_surcharge_amount FROM ".$this->getTable('sales/creditmemo')."");

$sql_surcharge_refund=mysql_query(
    "SELECT surcharge_amount_refunded FROM ".$this->getTable('sales/order')."");

$sql_base_surcharge_refund=mysql_query(
    "SELECT base_surcharge_amount_refunded FROM ".$this->getTable('sales/order')."");

if (!$sql_surcharge){

    mysql_query("ALTER TABLE  ".$this->getTable('sales/creditmemo')." ADD  surcharge_amount DECIMAL( 10, 2 ) NOT NULL;");

}

if (!$sql_base_surcharge){

    mysql_query("ALTER TABLE  ".$this->getTable('sales/creditmemo')." ADD  base_surcharge_amount DECIMAL( 10, 2 ) NOT NULL;");

}

if (!$sql_surcharge_refund){

    mysql_query("ALTER TABLE  ".$this->getTable('sales/order')." ADD  surcharge_amount_refunded DECIMAL( 10, 2 ) NOT NULL;");

}

if (!$sql_base_surcharge_refund){

    mysql_query("ALTER TABLE  ".$this->getTable('sales/order')." ADD  base_surcharge_amount_refunded DECIMAL( 10, 2 ) NOT NULL;");

}

$installer->endSetup();

/* $installer->run("

		ALTER TABLE  ".$this->getTable('sales/creditmemo')." ADD  surcharge_amount DECIMAL( 10, 2 ) NOT NULL;
		ALTER TABLE  ".$this->getTable('sales/creditmemo')." ADD  base_surcharge_amount DECIMAL( 10, 2 ) NOT NULL;
		
		ALTER TABLE  ".$this->getTable('sales/order')." ADD  surcharge_amount_refunded DECIMAL( 10, 2 ) NOT NULL;
		ALTER TABLE  ".$this->getTable('sales/order')." ADD  base_surcharge_amount_refunded DECIMAL( 10, 2 ) NOT NULL;

		"); */
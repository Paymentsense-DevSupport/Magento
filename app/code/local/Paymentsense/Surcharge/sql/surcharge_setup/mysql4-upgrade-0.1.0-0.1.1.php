<?php

$installer = $this;

$installer->startSetup();

$sql_surcharge=mysql_query(
    "SELECT surcharge_amount FROM ".$this->getTable('sales/quote_address')."");

$sql_base_surcharge=mysql_query(
    "SELECT base_surcharge_amount FROM ".$this->getTable('sales/quote_address')."");

if (!$sql_surcharge){

    mysql_query("ALTER TABLE  `".$this->getTable('sales/quote_address')."` ADD  `surcharge_amount` DECIMAL( 10, 2 ) NOT NULL;");

}

if (!$sql_base_surcharge){

    mysql_query("ALTER TABLE  `".$this->getTable('sales/quote_address')."` ADD  `base_surcharge_amount` DECIMAL( 10, 2 ) NOT NULL;");

}

$installer->endSetup();

/* $installer->run("

ALTER TABLE  ".$this->getTable('sales/quote_address')." ADD  surcharge_amount DECIMAL( 10, 2 ) NOT NULL;
ALTER TABLE  ".$this->getTable('sales/quote_address')." ADD  base_surcharge_amount DECIMAL( 10, 2 ) NOT NULL;


"); */
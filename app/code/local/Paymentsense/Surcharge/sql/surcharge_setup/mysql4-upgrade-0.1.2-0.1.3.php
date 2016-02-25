<?php

$installer = $this;

$installer->startSetup();

$sql_surcharge=mysql_query(
    "SELECT surcharge_amount FROM ".$this->getTable('sales/invoice')."");

$sql_base_surcharge=mysql_query(
    "SELECT base_surcharge_amount FROM ".$this->getTable('sales/invoice')."");

if (!$sql_surcharge){

    mysql_query("ALTER TABLE  `".$this->getTable('sales/invoice')."` ADD  `surcharge_amount` DECIMAL( 10, 2 ) NOT NULL;");

}

if (!$sql_base_surcharge){

    mysql_query("ALTER TABLE  `".$this->getTable('sales/invoice')."` ADD  `base_surcharge_amount` DECIMAL( 10, 2 ) NOT NULL;");

}

$installer->endSetup();

/* $installer->run("


            ALTER TABLE  ".$this->getTable('sales/invoice')." ADD  surcharge_amount DECIMAL( 10, 2 ) NOT NULL;

            ALTER TABLE  ".$this->getTable('sales/invoice')." ADD  base_surcharge_amount DECIMAL( 10, 2 ) NOT NULL;


		"); */
<?php

$installer = $this;

$installer->startSetup();

$installer->run("

		ALTER TABLE  `".$this->getTable('sales/order')."` ADD  `surcharge_amount_refunded` DECIMAL( 10, 2 ) NOT NULL;
		ALTER TABLE  `".$this->getTable('sales/order')."` ADD  `base_surcharge_amount_refunded` DECIMAL( 10, 2 ) NOT NULL;
		
		ALTER TABLE  `".$this->getTable('sales/creditmemo')."` ADD  `surcharge_amount` DECIMAL( 10, 2 ) NOT NULL;
		ALTER TABLE  `".$this->getTable('sales/creditmemo')."` ADD  `base_surcharge_amount` DECIMAL( 10, 2 ) NOT NULL;

		");

$installer->endSetup();

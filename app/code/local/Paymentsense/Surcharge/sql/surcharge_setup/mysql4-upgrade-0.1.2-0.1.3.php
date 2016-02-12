<?php

$installer = $this;

$installer->startSetup();

$installer->run("

		ALTER TABLE  `".$this->getTable('sales/invoice')."` ADD  `surcharge_amount` DECIMAL( 10, 2 ) NOT NULL;
		ALTER TABLE  `".$this->getTable('sales/invoice')."` ADD  `base_surcharge_amount` DECIMAL( 10, 2 ) NOT NULL;

		");

$installer->endSetup();

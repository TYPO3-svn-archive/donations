<?php

########################################################################
# Extension Manager/Repository config file for ext: "donations"
#
# Auto generated 01-04-2008 09:51
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Donations',
	'description' => 'Collect donations for your project(s) using online payment. Requires PHP 5.',
	'category' => 'plugin',
	'shy' => 0,
	'version' => '0.5.0',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'alpha',
	'uploadfolder' => 1,
	'createDirs' => 'uploads/tx_donations/rte/',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Tonni Aagesen, Francois Suter (Cobweb)',
	'author_email' => 'support@cobweb.ch',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'paymentlib' => '0.3.0',
			'moneylib' => '',
			'static_info_tables' => '',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:20:{s:9:"ChangeLog";s:4:"de93";s:10:"README.txt";s:4:"ee2d";s:12:"ext_icon.gif";s:4:"ac30";s:17:"ext_localconf.php";s:4:"a071";s:14:"ext_tables.php";s:4:"1602";s:14:"ext_tables.sql";s:4:"4a4e";s:30:"icon_tx_donations_deposits.gif";s:4:"76a9";s:30:"icon_tx_donations_projects.gif";s:4:"d564";s:33:"icon_tx_donations_projects__h.gif";s:4:"3161";s:34:"icon_tx_donations_projects__ht.gif";s:4:"2f2e";s:33:"icon_tx_donations_projects__t.gif";s:4:"ea1f";s:13:"locallang.xml";s:4:"e4bd";s:16:"locallang_db.xml";s:4:"fc89";s:7:"tca.php";s:4:"7531";s:14:"doc/manual.sxw";s:4:"5446";s:30:"pi1/class.tx_donations_pi1.php";s:4:"4f05";s:16:"pi1/example.tmpl";s:4:"7dc7";s:17:"pi1/locallang.xml";s:4:"ce3b";s:17:"pi1/validation.js";s:4:"1201";s:20:"pi1/static/setup.txt";s:4:"8ff1";}',
);

?>
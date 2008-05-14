<?php
/*
 * $Id$
 */
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_extMgm::allowTableOnStandardPages('tx_donations_projects');

$TCA['tx_donations_projects'] = array (
	'ctrl' => array (
		'title'     => 'LLL:EXT:donations/locallang_db.xml:tx_donations_projects',		
		'label'     => 'title',	
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY crdate',	
		'delete' => 'deleted',	
		'enablecolumns' => array (		
			'disabled' => 'hidden',	
			'starttime' => 'starttime',	
			'endtime' => 'endtime',	
			'fe_group' => 'fe_group',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_donations_projects.gif',
	),
	'feInterface' => array (
		'fe_admin_fieldList' => 'hidden, starttime, endtime, fe_group, title, logo, short_desc, long_desc, details_url, amount, currency, min_payment',
	)
);

$TCA['tx_donations_deposits'] = array (
	'ctrl' => array (
		'title'     => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits',		
		'label'     => 'cust_name',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY crdate',	
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_donations_deposits.gif',
	),
	'feInterface' => array (
		'fe_admin_fieldList' => 'project, cust_company, cust_name, cust_addr, cust_city, cust_zip, cust_country, cust_email, amount',
	)
);

t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key';
t3lib_extMgm::addPlugin(array('LLL:EXT:donations/locallang_db.xml:tt_content.list_type', $_EXTKEY.'_pi1'),'list_type');

t3lib_extMgm::addStaticFile($_EXTKEY,'pi1/static/','Donations');
?>
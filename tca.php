<?php
/*
 * $Id: tca.php 3449 2007-08-13 20:43:37Z fsuter $
 */
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_donations_projects'] = array (
	'ctrl' => $TCA['tx_donations_projects']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'hidden,starttime,endtime,fe_group,title,logo,short_desc,long_desc,details_url,amount,currency,min_payment'
	),
	'feInterface' => $TCA['tx_donations_projects']['feInterface'],
	'columns' => array (
		'hidden' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'starttime' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.starttime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'default'  => '0',
				'checkbox' => '0'
			)
		),
		'endtime' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.endtime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'checkbox' => '0',
				'default'  => '0',
				'range'    => array (
					'upper' => mktime(0, 0, 0, 12, 31, 2020),
					'lower' => mktime(0, 0, 0, date('m')-1, date('d'), date('Y'))
				)
			)
		),
		'fe_group' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.fe_group',
			'config'  => array (
				'type'  => 'select',
				'items' => array (
					array('', 0),
					array('LLL:EXT:lang/locallang_general.xml:LGL.hide_at_login', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.any_login', -2),
					array('LLL:EXT:lang/locallang_general.xml:LGL.usergroups', '--div--')
				),
				'foreign_table' => 'fe_groups'
			)
		),
		'title' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_projects.title',		
			'config' => Array (
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'required,trim',
			)
		),
		'logo' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_projects.logo',		
			'config' => Array (
				'type' => 'group',
				'internal_type' => 'file',
				'allowed' => 'gif,png,jpeg,jpg',	
				'max_size' => 500,	
				'uploadfolder' => 'uploads/tx_donations',
				'show_thumbs' => 1,	
				'size' => 1,	
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'short_desc' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_projects.short_desc',		
			'config' => Array (
				'type' => 'text',
				'cols' => '30',	
				'rows' => '3',
			)
		),
		'long_desc' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_projects.long_desc',		
			'config' => Array (
				'type' => 'text',
				'cols' => '30',
				'rows' => '5',
				'wizards' => Array(
					'_PADDING' => 2,
					'RTE' => array(
						'notNewRecords' => 1,
						'RTEonly' => 1,
						'type' => 'script',
						'title' => 'Full screen Rich Text Editing|Formatteret redigering i hele vinduet',
						'icon' => 'wizard_rte2.gif',
						'script' => 'wizard_rte.php',
					),
				),
			)
		),
		'details_url' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_projects.details_url',		
			'config' => Array (
				'type' => 'input',	
				'size' => '30',	
				'wizards' => Array(
					'_PADDING' => 2,
					'link' => Array(
						'type' => 'popup',
						'title' => 'Link',
						'icon' => 'link_popup.gif',
						'script' => 'browse_links.php?mode=wizard',
						'JSopenParams' => 'height=300,width=500,status=0,menubar=0,scrollbars=1'
					),
				),
				'eval' => 'nospace',
			)
		),
		'amount' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_projects.amount',		
			'config' => Array (
				'type' => 'input',	
				'size' => '12',	
				'eval' => 'required,double2,nospace',
			)
		),
		'currency' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_projects.currency',		
			'displayCond' => 'EXT:static_info_tables:LOADED:true',			
			'config' => Array (
				'type' => 'select',
/*
				'items' => Array (
					Array (' ', 0),
				),
*/
				'foreign_table' => 'static_currencies',	
				'foreign_table_where' => 'AND static_currencies.pid=0 ORDER BY static_currencies.cu_name_en ASC',	
				'size' => 1,	
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'min_payment' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_projects.min_payment',		
			'config' => Array (
				'type' => 'input',	
				'size' => '12',
				'eval' => 'double2,nospace',
			)
		),
	),
	'types' => array (
		'0' => array('showitem' => 'hidden;;1;;1-1-1, title;;;;2-2-2, logo;;;;3-3-3, short_desc, long_desc;;;richtext[cut|copy|paste|formatblock|textcolor|bold|italic|underline|left|center|right|orderedlist|unorderedlist|outdent|indent|link|table|image|line|chMode]:rte_transform[mode=ts_css|imgpath=uploads/tx_donations/rte/], details_url, amount, currency, min_payment')
	),
	'palettes' => array (
		'1' => array('showitem' => 'starttime, endtime, fe_group')
	)
);



$TCA['tx_donations_deposits'] = array (
	'ctrl' => $TCA['tx_donations_deposits']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'project,cust_company,cust_name,cust_addr,cust_city,cust_zip,cust_country,cust_email,amount'
	),
	'feInterface' => $TCA['tx_donations_deposits']['feInterface'],
	'columns' => array (
		'project_uid' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits.project_uid',		
			'config' => Array (
				'type' => 'none',
			)
		),
		'cust_company' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits.cust_company',		
			'config' => Array (
				'type' => 'none',
			)
		),
		'cust_name' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits.cust_name',		
			'config' => Array (
				'type' => 'none',
			)
		),
		'cust_addr' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits.cust_addr',		
			'config' => Array (
				'type' => 'none',
			)
		),
		'cust_city' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits.cust_city',		
			'config' => Array (
				'type' => 'none',
			)
		),
		'cust_zip' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits.cust_zip',		
			'config' => Array (
				'type' => 'none',
			)
		),
		'cust_country' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits.cust_country',		
			'config' => Array (
				'type' => 'none',
			)
		),
		'cust_email' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits.cust_email',		
			'config' => Array (
				'type' => 'none',
			)
		),
		'amount' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:donations/locallang_db.xml:tx_donations_deposits.amount',		
			'config' => Array (
				'type' => 'none',
			)
		),
        'paymentlib_trx_uid' => Array (
		    'exclude' => 0,
		    'label' => 'Payment details',
		    'config' => Array (
			    'type' => 'user',
			    'userFunc' => 'tx_paymentlib_tceforms->itemsProcFunc_paymentDetails',
		    )
	    ),
	),
	'types' => array (
		'0' => array('showitem' => 'project;;;;1-1-1, cust_company, cust_name, cust_addr, cust_city, cust_zip, cust_country, cust_email, amount, paymentlib_trx_uid')
	),
	'palettes' => array (
		'1' => array('showitem' => '')
	)
);
?>
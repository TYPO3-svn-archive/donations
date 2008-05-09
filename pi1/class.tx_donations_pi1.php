<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2007 Tonni Aagesen <t3dev@support.pil.dk>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 *
 *  $Id: class.tx_donations_pi1.php 3484 2007-09-12 07:31:55Z fsuter $
 ***************************************************************/

require_once(PATH_tslib.'class.tslib_pibase.php');

require_once(t3lib_extMgm::extPath('moneylib').'class.tx_moneylib.php');
require_once(t3lib_extMgm::extPath('paymentlib').'lib/class.tx_paymentlib_providerfactory.php');


/**
 * Plugin 'Donations' for the 'donations' extension.
 * Displays projects to donate to and various screens for the donation process
 *
 * @author	Tonni Aagesen <t3dev@support.pil.dk>, Francois Suter <support@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_donations
 */
class tx_donations_pi1 extends tslib_pibase {
    var $prefixId = 'tx_donations';		// Same as class name
    var $scriptRelPath = 'pi1/class.tx_donations_pi1.php';	// Path to this script relative to the extension dir.
    var $extKey = 'donations';	// The extension key.
    var $localconf;
    var $template = '';

    /**
     * The main method of the plugin which acts as a controller, dispatching to other methods
     * depending on the values of (or absence of) piVars
     *
     * @param	string		$content: The PlugIn content
     * @param	array		$conf: The PlugIn configuration
     * @return	The content that is displayed on the website
     */
	function main($content,$conf) {
		$this->localconf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1; // Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		$this->moneylibObj = t3lib_div::makeInstance('tx_moneylib');
		
		if ($errors = $this->init()) {
			$error = '<p>EXT: '.$this->extKey.' - '.$this->pi_getLL('configuration_error').':</p>';
			$error .= '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
			if (!empty($this->localconf['errorWrap.'])) $error = $this->cObj->stdWrap($error,$this->localconf['errorWrap.']);
			$content = $error;
		}
		else {
			$projectUid = intval($this->piVars['project_uid']);
			switch ($this->piVars['view']) {
				case 'confirm':
					$content = $this->confirmView($projectUid);
					break;
				case 'receipt':
					$content = $this->receiptView($projectUid);
					break;
				case 'single':
					$content = $this->singleView($projectUid);
					break;
				case 'donate':
					$content = $this->donateView($projectUid);
					break;
				default:
					if ($this->localconf['disableProjects'] == 1) {
						$content = $this->donateView($projectUid);
					}
					else {
						$content = $this->listView();
					}
					break;
			}
		}


// Wrap the whole result, with baseWrap if defined, else with standard pi_wrapInBaseClass() call

		if (isset($this->localconf['baseWrap.'])) {
			return $this->cObj->stdWrap($content,$this->localconf['baseWrap.']);
		}
		else {
			return $this->pi_wrapInBaseClass($content);
		}
	}

	/**
	 * This method displays the list of projects that can be donated to
	 * If the projects are not used, it switches to the donate form instead
	 *
	 * @return The content to be displayed
	 */
	function listView() {
		if ($this->localconf['disableProjects'] == 1) { // This shouldn't happen, but better make sure
			return $this->donateView(0);
		}
		else {
			$markers = array();
			$items = array();
			$subpart = $this->cObj->getSubpart($this->template, '###LISTVIEW_ITEM###');

			$whereClause = 'paid < amount';
			if (!empty($this->cObj->data['pages'])) {
				$pages = explode(',',$this->cObj->data['pages']);
				$whereClause .= " AND pid = '".$pages[0]."'";
			}
			$whereClause .= $this->cObj->enableFields('tx_donations_projects');
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery ('*', 'tx_donations_projects', $whereClause);

			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$currency = $this->getCurrency($row['currency']);
				$markers['###TITLE###'] = $row['title'];
				$markers['###SHORT_DESC###'] = $row['short_desc'];
				$imagePath = 'uploads/'.$this->prefixId.'/'.$row['logo'];
				$markers['###LOGO###'] = $this->cObj->IMAGE(array('file' => $imagePath));
				$markers['###AMOUNT_MIN###'] = $this->moneylibObj->format(intval($row['min_payment'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
				$markers['###AMOUNT_DONATED###'] = $this->moneylibObj->format(intval($row['paid'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
				$markers['###AMOUNT_NEEDED###'] = $this->moneylibObj->format(intval($row['amount'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
				$markers['###CURRENCY###'] = $currency['cu_iso_3'];
				$markers['###DETAILS_LINK###'] = $this->pi_linkTP($this->pi_getLL('details_link'), array('tx_donations[view]' => 'single', 'tx_donations[project_uid]' => $row['uid']));
				$markers['###DONATE_LINK###'] = $this->pi_linkTP($this->pi_getLL('donate_link'), array('tx_donations[view]' => 'donate', 'tx_donations[project_uid]' => $row['uid']));
				$items[] = $this->cObj->substituteMarkerArray($subpart, $markers);
			}

			$subpart = $this->cObj->getSubpart($this->template, '###LISTVIEW###');
			return $this->cObj->substituteMarkerArray($subpart, array('###ITEMS###' => implode('', $items)));
		}
	}

	/**
	 * This method displays the detailed view of a project
	 *
	 * @param  integer	uid of the project to display
	 * @return The content to be displayed
	 */
	function singleView($uid) {
		if (empty($uid)) { // Display something else if the project's uid is empty (this shouldn't happen really)
			if ($this->localconf['disableProjects'] == 1) { // If we're not using projects, display the donate form
				return $this->donateView(0);
			}
			else { // Go back to projects list view
				return $this->listView();
			}
		}
		else {
			$markers = array();
			$subpart = $this->cObj->getSubpart($this->template, '###SINGLEVIEW###');
			$row = $this->pi_getRecord('tx_donations_projects', $uid);
			$currency = $this->getCurrency($row['currency']);
			$markers['###TITLE###'] = $row['title'];
			$markers['###SHORT_DESC###'] = $row['short_desc'];
			$markers['###LONG_DESC###'] = $row['long_desc'];
			$imagePath = 'uploads/'.$this->prefixId.'/'.$row['logo'];
			$markers['###LOGO###'] = $this->cObj->IMAGE(array('file' => $imagePath)); // $row['logo'];
			$markers['###AMOUNT_MIN###'] = $this->moneylibObj->format(intval($row['min_payment'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
			$markers['###AMOUNT_DONATED###'] = $this->moneylibObj->format (intval($row['paid'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
			$markers['###AMOUNT_NEEDED###'] = $this->moneylibObj->format (intval($row['amount'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
			$markers['###CURRENCY###'] = $currency['cu_iso_3'];
			$markers['###DONATE_LINK###'] = $this->pi_linkTP($this->pi_getLL('donate_link'), array('tx_donations[view]' => 'donate', 'tx_donations[project_uid]' => $row['uid']));
			$markers['###BACK_LINK###'] = $this->pi_linkTP($this->pi_getLL('back_list_link'), array());
			return $this->cObj->substituteMarkerArray($subpart, $markers);
		}
	}

	/**
	 * This method displays the form for donation input
	 *
	 * @param integer	uid of the selected project (may be empty if projects are not used)
	 * @return The content to be displayed
	 */
	function donateView($uid) {
		// Reset session vars
		$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_donations_payment_reference', false);
		$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_donations_payment_piVars', array());

		$markers = array();
		$subpart = $this->cObj->getSubpart($this->template, '###DONATEVIEW###');

// Get project info, if a project is defined

		$row = $this->getProject($uid);
		$currency = $this->getCurrency($row['currency']);

// Get the list of payment methods

        $providerFactoryObj = tx_paymentlib_providerfactory::getInstance();
        if ($providerObjectsArr = $providerFactoryObj->getProviderObjects()) {
            $paymethods = t3lib_div::trimExplode(',', $this->localconf['paymethods']);
            $paymentMethodsArr = array();
            foreach ($providerObjectsArr as $providerObj) {
                $tmpArr = $providerObj->getAvailablePaymentMethods();
                $keys = array_intersect(array_keys($tmpArr), $paymethods);
                foreach ($keys as $key) {
                    $paymentMethodsArr[$key] = $tmpArr[$key];
                }
            }

// Assemble list of payment method options

			$selectedPayment = $this->getPiVars('paymethod');
            foreach ($paymentMethodsArr as $paymentMethodKey => $paymentMethodConf) {
	            $paymentMethodConf['iconpath'] = str_replace('EXT:', '', $paymentMethodConf['iconpath']);
	            $label = htmlspecialchars($GLOBALS['TSFE']->sL($paymentMethodConf['label']));
	            $options .= '<div><input type="radio" name="tx_donations[paymethod]" id="'.$paymentMethodKey.'" value="'.$paymentMethodKey.'"';
	            if (!empty($selectedPayment) && $paymentMethodKey == $selectedPayment) $options .= ' checked="checked"';
	            $options .= ' /> ';
	            $options .= '<label for="'.$paymentMethodKey.'">';
	            if (!empty($paymentMethodConf['iconpath'])) $options .= '<img src="/typo3conf/ext/' . $paymentMethodConf['iconpath'] . '" alt="'.$label.'" /> ';
	            $options .= $label.'</label></div>';
	        }

// Include JavaScript for checking form input

			$GLOBALS['TSFE']->additionalHeaderData[$this->extKey] = '<script type="text/javascript" src="'.t3lib_extMgm::siteRelPath($this->extKey).'pi1/validation.js"></script>';

// If an error occurred, prepare error message

			if (!empty($this->piVars['error'])) {
				$errorMessage = $this->pi_getLL('donation_error'.($this->piVars['error']));
				$markers['###ERROR_MESSAGE###'] = $this->cObj->stdWrap($errorMessage,$this->localconf['errorWrap.']);
			}
			else {
				$markers['###ERROR_MESSAGE###'] = '';
			}

// Display donation form

			$markers['###FORM_URL###'] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
			$markers['###FORM_NAME###'] = $this->localconf['formName'];
			$markers['###PAYMETHODS###'] = $options;
			if (empty($this->localconf['donateView.']['projectTitle.'])) {
				$markers['###PROJECT_TITLE###'] = '';
			}
			else {
				$markers['###PROJECT_TITLE###'] = $this->cObj->stdWrap('',$this->localconf['donateView.']['projectTitle.']);
			}
			if (empty($uid)) {
				$markers['###AMOUNT_MIN###'] = '';
				$markers['###AMOUNT_MAX###'] = $currency['cu_iso_3'];
			}
			else {
				$formattedValue = $this->moneylibObj->format(intval($row['min_payment'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], true);
				if (empty($this->localconf['donateView.']['amountMin.'])) {
					$markers['###AMOUNT_MIN###'] = $formattedValue;
				}
				else {
					$markers['###AMOUNT_MIN###'] = $this->cObj->stdWrap($formattedValue,$this->localconf['donateView.']['amountMin.']);
				}
				$formattedValue = $this->moneylibObj->format(intval(($row['amount'] - $row['paid']) * $currency['cu_sub_divisor']), $currency['cu_iso_3'], true);
				if (empty($this->localconf['donateView.']['amountMax.'])) {
					$markers['###AMOUNT_MAX###'] = $formattedValue;
				}
				else {
					$markers['###AMOUNT_MAX###'] = $this->cObj->stdWrap($formattedValue,$this->localconf['donateView.']['amountMax.']);
				}
			}
			$amount = $this->getPiVars('amount');
			$markers['###AMOUNT_VAL###'] = (empty($amount)) ? '' : $amount;
			if (isset($currency)) $markers['###CURRENCY###'] = $currency['cu_iso_3'];
			
			$markers['###HIDDEN_FIELDS###'] = '<input type="hidden" name="tx_donations[view]" value="confirm" />';
			if (isset($row['uid'])) {
				$markers['###HIDDEN_FIELDS###'] .= '<input type="hidden" name="tx_donations[project_uid]" value="'.$row['uid'].'" />';
			}
			$company = $this->getPiVars('company');
			$name = $this->getPiVars('name');
			$addr = $this->getPiVars('addr');
			$zip = $this->getPiVars('zip');
			$city = $this->getPiVars('city');
			$country = $this->getPiVars('country');
			$email = $this->getPiVars('email');
			$markers['###COMPANY_VAL###'] = (empty($company)) ? '' : $company;
			$markers['###NAME_VAL###'] = (empty($name)) ? '' : $name;
			$markers['###ADDR_VAL###'] = (empty($addr)) ? '' : $addr;
			$markers['###ZIP_VAL###'] = (empty($zip)) ? '' : $zip;
			$markers['###CITY_VAL###'] = (empty($city)) ? '' : $city;
			$markers['###COUNTRY_VAL###'] = (empty($country)) ? '' : $country;
			$markers['###EMAIL_VAL###'] = (empty($email)) ? '' : $email;
//			$markers['###BUTTONS###'] = '<input type="submit" name="tx_donations[confirm]" value="'.($this->pi_getLL('continue')).'" />';
			$markers['###BUTTONS###'] = '<input type="submit" name="submit" value="'.($this->pi_getLL('continue')).'" />';
			if (empty($uid)) {
				$markers['###BACK_LINK###'] = '';
			}
			else {
				$markers['###BACK_LINK###'] = $this->pi_linkTP($this->pi_getLL('back_list_link'), array());
			}
			return $this->cObj->substituteMarkerArray($subpart, $markers);
		}
		else {
			return $this->cObj->stdWrap($this->pi_getLL('payment_methods_error'),$this->localconf['errorWrap.']);
		}
	}

	/**
	 * This method displays a confirmation screen before submitting the payment
	 *
	 * @param integer	uid of the project to donate to
	 * @return The content to be displayed
	 */
	function confirmView($uid) {

// A reference must be defined so that if the payment has alreday been processed, it cannot be processed a second time

		if (!$paymentReference = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_donations_payment_reference')) {
			$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_donations_payment_reference', time());
			$paymentReference = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_donations_payment_reference');
		}

// Plugin variables must be stored in session for when the payment process redirects to the donations process
// If values are not stored yet, do it. Else read them.

		if (!$GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_donations_payment_piVars')) {
			$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_donations_payment_piVars', $this->piVars);
		}
		else {
			$localPiVars = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_donations_payment_piVars');
			foreach ($localPiVars as $key => $val) {
				$this->piVars[$key] = !empty($this->piVars[$key]) ? $this->piVars[$key] : $localPiVars[$key];
			}
		}

// Get project info, if a project is defined

		$row = $this->getProject($uid);
		$currency = $this->getCurrency($row['currency']);
        $rawAmount = $this->getPiVars('amount');
        $amount = $rawAmount * $currency['cu_sub_divisor'];
        $message = '';

// Check form input. Some fields should not be empty:
//	- payment method, amount, name, email (this should be configurable in the future)

		$errorCode = 0;
		if (empty($this->piVars['paymethod']) || empty($rawAmount) || empty($this->piVars['name']) || empty($this->piVars['email'])) {
			$errorCode = 1;
		}
		else {
			if ($rawAmount < 0) {
				$errorCode = 2;
			}
			else {
				if (!empty($uid)) {
					if ($rawAmount < $row['min_payment']) {
						$errorCode = 3;
					}
					elseif ($rawAmount > $row['amount'] - $row['paid']) {
						$errorCode = 4;
					}
				}
			}
		}
		if ($errorCode > 0) {
			$url = t3lib_div::locationHeaderUrl($this->pi_getPageLink($GLOBALS['TSFE']->id,'',array('tx_donations[view]' => 'donate', 'tx_donations[project_uid]' => $uid, 'tx_donations[error]' => $errorCode)));
			header('Location: '.$url);
		}

// Get template

		$markers = array();
		$subpart = $this->cObj->getSubpart($this->template, '###CONFIRMVIEW###');

// Get payment method information, in particular hidden or visible fields

		$providerFactoryObj = tx_paymentlib_providerfactory::getInstance();
		$providerObj = $providerFactoryObj->getProviderObjectByPaymentMethod($this->getPiVars('paymethod'));
        
		$methods = $providerObj->getAvailablePaymentMethods();
		$paymethodLabel = $methods[$this->getPiVars('paymethod')]['label'];

		$ok =  $providerObj->transaction_init(TX_PAYMENTLIB_TRANSACTION_ACTION_AUTHORIZEANDTRANSFER, $this->getPiVars('paymethod'), TX_PAYMENTLIB_GATEWAYMODE_FORM, $this->extKey);
		if (!$ok) return $this->pi_getLL('transaction_init_failed');

// The confirm page can be called again if the payment failed
// If that is the case, issue error message

		if (is_array($transactionResultsArr = $providerObj->transaction_getResults($paymentReference))) {
			$message = $this->pi_getLL('payment_declined');
		}

		$transactionDetailsArr = array (
			'transaction' => array (
				'amount' => $amount,
				'currency' => $currency['cu_iso_3'],
			),
			'options' => array (
				'reference' => $paymentReference,
			),
		);
		$ok = $providerObj->transaction_setDetails($transactionDetailsArr);
		if (!$ok) return $this->pi_getLL('transaction_settings_failed');

// Set response URLs

		$baseURI = ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') ? 'http://' : 'https://') . t3lib_div::getThisUrl();
		$providerObj->transaction_setErrorPage($baseURI.$this->pi_getPageLink($GLOBALS['TSFE']->id, '', array('tx_donations[view]' => 'confirm', 'tx_donations[project_uid]' => $uid)));
		$providerObj->transaction_setOKPage($baseURI.$this->pi_getPageLink($GLOBALS['TSFE']->id, '', array('tx_donations[view]' => 'receipt', 'tx_donations[project_uid]' => $uid)));

		$hiddenFields = array();
		if ($hf = $providerObj->transaction_formGetHiddenFields()) {
			foreach ($hf as $name => $value) {
				$hiddenFields[] = '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
			}
		}

// Add to hidden fields all input data in case user presses the back button

		$hiddenFields[] = '<input type="hidden" name="tx_donations[project_uid]" value="'.$uid.'" />';
		$hiddenFields[] = '<input type="hidden" name="tx_donations[paymethod]" value="'.$this->getPiVars('paymethod').'" />';
		$hiddenFields[] = '<input type="hidden" name="tx_donations[amount]" value="'.$rawAmount.'" />';
		$hiddenFields[] = '<input type="hidden" name="tx_donations[company]" value="'.$this->getPiVars('company').'" />';
		$hiddenFields[] = '<input type="hidden" name="tx_donations[name]" value="'.$this->getPiVars('name').'" />';
		$hiddenFields[] = '<input type="hidden" name="tx_donations[addr]" value="'.$this->getPiVars('addr').'" />';
		$hiddenFields[] = '<input type="hidden" name="tx_donations[zip]" value="'.$this->getPiVars('zip').'" />';
		$hiddenFields[] = '<input type="hidden" name="tx_donations[city]" value="'.$this->getPiVars('city').'" />';
		$hiddenFields[] = '<input type="hidden" name="tx_donations[country]" value="'.$this->getPiVars('country').'" />';
		$hiddenFields[] = '<input type="hidden" name="tx_donations[email]" value="'.$this->getPiVars('email').'" />';

		$visibleFields = array();
			if ($vf = $providerObj->transaction_formGetVisibleFields()) {
			foreach ($vf as $name => $field) {
				$visibleFields[] = '<tr><td>'.$GLOBALS['TSFE']->sL($field['label']).'</td><td><input type="'.$field['config']['type'].'" name="'.$name.'" size="'.$field['config']['size'].'" maxlength="'.$field['config']['max'].'"  /></td></tr>';
			}
		}

		if (!empty($message)) {
			$markers['###ERROR_MESSAGE###'] = $this->cObj->stdWrap($message,$this->localconf['errorWrap.']);
		}
		else {
			$markers['###ERROR_MESSAGE###'] = '';
		}
		$markers['###FORM_URL###'] = $providerObj->transaction_formGetActionURI();
		$markers['###FORM_NAME###'] = $this->localconf['formName'];
		$markers['###FORM_FORM_PARAMS###'] = $providerObj->transaction_formGetFormParms();
		$backURL = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', array('tx_donations[view]' => 'donate'));
//		$markers['###BUTTONS###'] = '<input type="submit" id="backButton" name="tx_donations[donate]" value="'.$this->pi_getLL('back').'" onclick="this.form.action=\''.$backURL.'\'" disabled="true" />';
		$markers['###BUTTONS###'] = '<input type="submit" id="backButton" name="back" value="'.$this->pi_getLL('back').'" onclick="this.form.action=\''.$backURL.'\'" disabled="true" />';
		$markers['###BUTTONS###'] .= '&nbsp;<input type="submit" name="submit" value="'.$this->pi_getLL('confirm').'" '.($providerObj->transaction_formGetSubmitParms()).' />';
		$markers['###BUTTONS###'] .= '<script>document.forms["donateForm"].backButton.disabled=false;</script><noscript><p>'.$this->pi_getLL('no_javascript_message').'</p></noscript>';
		if (empty($this->localconf['confirmView.']['projectTitle.'])) {
			$markers['###PROJECT_TITLE###'] = '';
		}
		else {
			$markers['###PROJECT_TITLE###'] = $this->cObj->stdWrap('',$this->localconf['confirmView.']['projectTitle.']);
		}

// Display input data for confirmation

		$markers['###PAYMETHOD###'] = $GLOBALS['TSFE']->sL($paymethodLabel);
		$markers['###AMOUNT_VAL###'] = $this->moneylibObj->format($amount, $currency['cu_iso_3'], false);
		$markers['###CURRENCY###'] = $currency['cu_iso_3'];

		$markers['###PAYMENT_DETAILS###'] = implode('', $visibleFields);

		$markers['###COMPANY_VAL###'] = $this->getPiVars('company');
		$markers['###NAME_VAL###'] = $this->getPiVars('name');
		$markers['###ADDR_VAL###'] = $this->getPiVars('addr');
		$markers['###ZIP_VAL###'] = $this->getPiVars('zip');
		$markers['###CITY_VAL###'] = $this->getPiVars('city');
		$markers['###COUNTRY_VAL###'] = $this->getPiVars('country');
		$markers['###EMAIL_VAL###'] = $this->getPiVars('email');
		$markers['###HIDDEN_FIELDS###'] = implode("\n", $hiddenFields);
		return $this->cObj->substituteMarkerArray($subpart, $markers);
	}

	/**
	 * This method displays the receipt screen when a payment has successfully been processed
	 *
	 * @param integer	uid of the project to donate to
	 * @return The content to be displayed
	 */
	function receiptView($uid) {
		$paymentReference = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_donations_payment_reference');

		if (!$GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_donations_payment_piVars')) {
			$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_donations_payment_piVars', $this->piVars);
		}
		else {
			$localPiVars = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_donations_payment_piVars');
			foreach ($localPiVars as $key => $val) {
				$this->piVars[$key] = !empty($this->piVars[$key]) ? $this->piVars[$key] : $localPiVars[$key];
			}
		}

		$markers = array();
		$subpart = $this->cObj->getSubpart($this->template, '###RECEIPTVIEW###');

// Get project info, if a project is defined

		$row = $this->getProject($uid);
		$currency = $this->getCurrency($row['currency']);
		$amount = $this->getPiVars('amount');

		if ($paymentReference) {
			$providerFactoryObj = tx_paymentlib_providerfactory::getInstance();
			$providerObj = $providerFactoryObj->getProviderObjectByPaymentMethod($this->getPiVars('paymethod'));
			$methods = $providerObj->getAvailablePaymentMethods();
			$paymethodLabel = $methods[$this->getPiVars('paymethod')]['label'];
			$providerObj->transaction_init (TX_PAYMENTLIB_TRANSACTION_ACTION_AUTHORIZEANDTRANSFER, $this->getPiVars('paymethod'), TX_PAYMENTLIB_GATEWAYMODE_FORM, $this->extKey);
			$transactionResultsArr = $providerObj->transaction_getResults($paymentReference);

// Store donation record

			$fields = array ();
			$time = time();
			$fields['tstamp'] = $time;
			$fields['crdate'] = $time;
			$fields['pid'] = $this->localconf['depositsPID'];
			$fields['cruser_id'] = '';
			$fields['project_uid'] = intval($uid);
			$fields['cust_company'] = $this->getPiVars('company');
			$fields['cust_name'] = $this->getPiVars('name');
			$fields['cust_addr'] = $this->getPiVars('addr');
			$fields['cust_city'] = $this->getPiVars('city');
			$fields['cust_zip'] = $this->getPiVars('zip');
			$fields['cust_country'] = $this->getPiVars('country');
			$fields['cust_email'] = $this->getPiVars('email');
			$fields['amount'] = $amount;
			$fields['paymentlib_trx_uid'] = $transactionResultsArr['uid'];
			$dbResult = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_donations_deposits', $fields);

// Update project total

			if (!empty($uid)) {
				$dbResult = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_donations_projects', 'uid = '.$GLOBALS['TYPO3_DB']->fullQuoteStr($uid,''), array('paid' => $row['paid'] + $amount));
			}
		}

		if (empty($this->localconf['receiptView.']['projectTitle.'])) {
			$markers['###PROJECT_TITLE###'] = '';
		}
		else {
			$markers['###PROJECT_TITLE###'] = $this->cObj->stdWrap('',$this->localconf['receiptView.']['projectTitle.']);
		}
		$markers['###MESSAGE###'] = $this->cObj->cObjGetSingle($this->localconf['thankMessage'], $this->localconf['thankMessage.'], 'thankMessage');

		$markers['###PAYMENT_REFERENCE###'] = $paymentReference;
		$markers['###PAYMETHOD###'] = $GLOBALS['TSFE']->sL($paymethodLabel);
		$markers['###AMOUNT_VAL###'] = $this->moneylibObj->format($amount * $currency['cu_sub_divisor'], $currency['cu_iso_3'], false);
		$markers['###CURRENCY###'] = $currency['cu_iso_3'];

		$markers['###COMPANY_VAL###'] = $this->getPiVars('company');
		$markers['###NAME_VAL###'] = $this->getPiVars('name');
		$markers['###ADDR_VAL###'] = $this->getPiVars('addr');
		$markers['###ZIP_VAL###'] = $this->getPiVars('zip');
		$markers['###CITY_VAL###'] = $this->getPiVars('city');
		$markers['###COUNTRY_VAL###'] = $this->getPiVars('country');
		$markers['###EMAIL_VAL###'] = $this->getPiVars('email');

// Prepare markers for confirmation mails, if necessary

		if (!empty($this->localconf['mail.']['sendToAdmin']) && !empty($this->localconf['mail.']['sendToUser'])) {
			require_once(PATH_t3lib.'class.t3lib_htmlmail.php');
			$mailMarkers = $markers;
			$mailMarkers['###PROJECT_TITLE###'] = (empty($uid)) ? '-' : $row['title'];
			$mailMarkers['###DATE_VAL###'] = $this->cObj->stdWrap(time(),$this->localconf['mail.']['dateWrap.']);

// Send mail to admin

			if (!empty($this->localconf['mail.']['sendToAdmin'])) {
				$mailMarkers['###MESSAGE###'] = $this->localconf['mail.']['adminMessage'];
				$mailSubpart = $this->cObj->getSubpart($this->template, '###ADMINMAIL###');
				$mailText = $this->cObj->substituteMarkerArray($mailSubpart, $mailMarkers);
				$adminMailObj = t3lib_div::makeInstance('t3lib_htmlmail');
				$adminMailObj->start();
				$adminMailObj->subject = $this->localconf['mail.']['adminSubject'];
				$adminMailObj->from_email = $this->localconf['mail.']['senderMail'];
				$adminMailObj->from_name = $this->localconf['mail.']['senderName'];
				$adminMailObj->replyto_email = $this->localconf['mail.']['senderMail'];
				$adminMailObj->replyto_name = $this->localconf['mail.']['senderName'];
				$adminMailObj->returnPath = $this->localconf['mail.']['senderMail'];
				$adminMailObj->setPlain($mailText); 
				$adminMailObj->send($this->localconf['mail.']['adminMail']);	
			}

// Send mail to user

			if (!empty($this->localconf['mail.']['sendToUser'])) {
				$mailMarkers['###MESSAGE###'] = $this->localconf['mail.']['userMessage'];
				$mailSubpart = $this->cObj->getSubpart($this->template, '###USERMAIL###');
				$mailText = $this->cObj->substituteMarkerArray($mailSubpart, $mailMarkers);
				$userMailObj = t3lib_div::makeInstance('t3lib_htmlmail');
				$userMailObj->start();
				$userMailObj->subject = $this->localconf['mail.']['userSubject'];
				$userMailObj->from_email = $this->localconf['mail.']['senderMail'];
				$userMailObj->from_name = $this->localconf['mail.']['senderName'];
				$userMailObj->replyto_email = $this->localconf['mail.']['senderMail'];
				$userMailObj->replyto_name = $this->localconf['mail.']['senderName'];
				$userMailObj->returnPath = $this->localconf['mail.']['senderMail'];
				$userMailObj->setPlain($mailText); 
				$userMailObj->send($this->getPiVars('email'));	
			}
		}

/*
TODO
Snippet from th_mailformplus
Check out rules and see what to apply

					# since 18.10.2005: prevent mail injection (reported by Joerg Schoppet - thx!)
					# subject and email_header are checked for mail injection as well before
					if (strstr($mailto, '@') && !eregi("\r",$mailto) && !eregi("\n",$mailto)) {
						$emailObj->send($mailto);	
					}
 */
		return $this->cObj->substituteMarkerArray($subpart, $markers);
	}

	/**
	 * This method returns a piVar first making sure that it is defined and not false, not 0 and not an empty string
	 *
	 * @param string	the name of the variable to fetch
	 * @return The value of the variable or an empty string if empty
	 */
	function getPiVars($name) {
		return !empty($this->piVars[$name]) ? $this->piVars[$name] : '';
	}

	/**
	 * This method returns the database record for the project that is being donated to
	 *
	 * @param integer	uid of the project
	 * @return Associative array of the corresponding record (or empty array if record not found)
	 */
	function getProject($uid) {
		if (empty($uid)) { // If no project is defined, initialise empty array
			$row = array();
		}
		else {
			$row = $this->pi_getRecord('tx_donations_projects', $uid);
			$this->cObj->data['project'] = $row['title'];
		}
		return $row;
	}

	/**
	 * This method returns the database record for the currency used for the project or the donation
	 *
	 * @param integer	uid of the currency
	 * @return Associative array of the corresponding record (or empty array if record not found)
	 */
	function getCurrency($uid) {
		$currency = array();
		if (empty($uid)) { // If no uid is available try to get the default currency (as defined in TS setup)
			if (!empty($this->localconf['defaultCurrency'])) {
				$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','static_currencies', "cu_iso_3 = '".$this->localconf['defaultCurrency']."'");
				if ($result) $currency = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
			}
		}
		else {
			$currency = $this->pi_getRecord('static_currencies', $uid);
		}
		return $currency;
	}

	/**
	 * This method detects whether a given string is UTF-8 encoded or not
	 * (taken from a comment from chris@w3style.co.uk on http://www.php.net/mb_detect_encoding)
	 *
	 * @param string	string to test
	 * @return true if string is UTF-8, false otherwise
	 */
	function is_utf8($string) {
		return preg_match('%(?:
		[\xC2-\xDF][\x80-\xBF]        # non-overlong 2-byte
		|\xE0[\xA0-\xBF][\x80-\xBF]               # excluding overlongs
		|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}      # straight 3-byte
		|\xED[\x80-\x9F][\x80-\xBF]               # excluding surrogates
		|\xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
		|[\xF1-\xF3][\x80-\xBF]{3}                  # planes 4-15
		|\xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
		)+%xs', $string);
	}

	/**
     * This method initialises the template and the localisation stuff
     *
     * @return		mixed		Returns an array with error messages if errors detected, otherwise boolean false
     */
    function init() {

        $confErrMsgs = array();

        // Get template
        $this->template = trim($this->cObj->fileResource($this->localconf['template']));
        if ($GLOBALS['TSFE']->renderCharset == 'utf-8' && !$this->is_utf8($this->template)) {
            $localCharset = !empty($GLOBALS['TSFE']->csConvObj->charSetArray[$this->LLkey]) ?  $GLOBALS['TSFE']->csConvObj->charSetArray[$this->LLkey] : 'iso-8859-1';
            $this->template = trim($GLOBALS['TSFE']->csConvObj->utf8_encode($this->template, $localCharset));
        }
        if (empty($this->template)) {
            $confErrMsgs[] = $this->pi_getLL('no_template');
        }
         
        return count($confErrMsgs) >= 0 ? $confErrMsgs : false;
    }
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/donations/pi1/class.tx_donations_pi1.php'])	{
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/donations/pi1/class.tx_donations_pi1.php']);
}

?>
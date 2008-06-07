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
 *  $Id$
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
	public $prefixId = 'tx_donations';		// Same as class name
	public $scriptRelPath = 'pi1/class.tx_donations_pi1.php';	// Path to this script relative to the extension dir.
	public $extKey = 'donations';	// The extension key.
	protected $template = '';
	
	/**
	 * The main method of the plugin which acts as a controller, dispatching to other methods
	 * depending on the values of (or absence of) piVars
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	public function main($content,$conf) {		
		if ($errors = $this->init($conf)) {
			$error = '<p>EXT: '.$this->extKey.' - '.$this->pi_getLL('configuration_error').':</p>';
			$error .= '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
			if (!empty($this->conf['errorWrap.'])) $error = $this->cObj->stdWrap($error,$this->conf['errorWrap.']);
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
					if ($this->conf['disableProjects'] == 1) {
						$content = $this->donateView($projectUid);
					}
					else {
						$content = $this->listView();
					}
					break;
			}
		}


// Wrap the whole result, with baseWrap if defined, else with standard pi_wrapInBaseClass() call

		if (isset($this->conf['baseWrap.'])) {
			return $this->cObj->stdWrap($content,$this->conf['baseWrap.']);
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
	protected function listView() {
		if ($this->conf['disableProjects'] == 1) { // This shouldn't happen, but better make sure
			return $this->donateView(0);
		}
		else {
			$markers = array();
			$items = array();
			$subpart = $this->cObj->getSubpart($this->template, '###LISTVIEW_ITEM###');

			$whereClause = '(paid < amount OR amount = 0)';
			if (!empty($this->cObj->data['pages'])) {
				$pages = explode(',',$this->cObj->data['pages']);
				$whereClause .= " AND pid = '".$pages[0]."'";
			}
			$whereClause .= $this->cObj->enableFields('tx_donations_projects');
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery ('*', 'tx_donations_projects', $whereClause);

			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$currency = $this->getCurrency($row['currency']);
				$markers['TITLE'] = $row['title'];
				$markers['SHORT_DESC'] = $row['short_desc'];
				$imagePath = 'uploads/'.$this->prefixId.'/'.$row['logo'];
				$markers['LOGO'] = $this->cObj->IMAGE(array('file' => $imagePath));
				$markers['AMOUNT_MIN'] = $this->moneylibObj->format(intval($row['min_payment'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
				$markers['AMOUNT_DONATED'] = $this->moneylibObj->format(intval($row['paid'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
				$markers['AMOUNT_NEEDED'] = $this->moneylibObj->format(intval($row['amount'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
				$markers['CURRENCY'] = $currency['cu_iso_3'];
				$markers['DETAILS_LINK'] = $this->pi_linkTP($this->pi_getLL('details_link'), array('tx_donations[view]' => 'single', 'tx_donations[project_uid]' => $row['uid']));
				$markers['DONATE_LINK'] = $this->pi_linkTP($this->pi_getLL('donate_link'), array('tx_donations[view]' => 'donate', 'tx_donations[project_uid]' => $row['uid']));
				$items[] = $this->substituteMarkerArray($subpart, $markers, '###|###');
			}

			$subpart = $this->cObj->getSubpart($this->template, '###LISTVIEW###');
			return $this->substituteMarkerArray($subpart, array('ITEMS' => implode('', $items)), '###|###');
		}
	}

	/**
	 * This method displays the detailed view of a project
	 *
	 * @param  integer	uid of the project to display
	 * @return The content to be displayed
	 */
	protected function singleView($uid) {
		if (empty($uid)) { // Display something else if the project's uid is empty (this shouldn't happen really)
			if ($this->conf['disableProjects'] == 1) { // If we're not using projects, display the donate form
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
			$markers['TITLE'] = $row['title'];
			$markers['SHORT_DESC'] = $row['short_desc'];
			$markers['LONG_DESC'] = $row['long_desc'];
			$imagePath = 'uploads/'.$this->prefixId.'/'.$row['logo'];
			$markers['LOGO'] = $this->cObj->IMAGE(array('file' => $imagePath)); // $row['logo'];
			$markers['AMOUNT_MIN'] = $this->moneylibObj->format(intval($row['min_payment'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
			$markers['AMOUNT_DONATED'] = $this->moneylibObj->format (intval($row['paid'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
			$markers['AMOUNT_NEEDED'] = $this->moneylibObj->format (intval($row['amount'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], false);
			$markers['CURRENCY'] = $currency['cu_iso_3'];
			$markers['DONATE_LINK'] = $this->pi_linkTP($this->pi_getLL('donate_link'), array('tx_donations[view]' => 'donate', 'tx_donations[project_uid]' => $row['uid']));
			$markers['BACK_LINK'] = $this->pi_linkTP($this->pi_getLL('back_list_link'), array());
			return $this->substituteMarkerArray($subpart, $markers, '###|###');
		}
	}

	/**
	 * This method displays the form for donation input
	 *
	 * @param integer	uid of the selected project (may be empty if projects are not used)
	 * @return The content to be displayed
	 */
	protected function donateView($uid) {
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
			$paymethods = t3lib_div::trimExplode(',', $this->conf['paymethods']);
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
				if (!empty($selectedPayment) && $paymentMethodKey == $selectedPayment) {
					$options .= ' checked="checked"';
				}
				$options .= ' /> ';
				$options .= '<label for="'.$paymentMethodKey.'">';
				if (!empty($paymentMethodConf['iconpath'])) {
					$options .= '<img src="/typo3conf/ext/' . $paymentMethodConf['iconpath'] . '" alt="'.$label.'" /> ';
				}
				$options .= $label.'</label></div>';
			}

				// Include JavaScript for checking form input
			$GLOBALS['TSFE']->additionalHeaderData[$this->extKey] = '<script type="text/javascript" src="'.t3lib_extMgm::siteRelPath($this->extKey).'pi1/validation.js"></script>';

// If an error occurred, prepare error message

			if (!empty($this->piVars['error'])) {
				$errorMessage = $this->pi_getLL('donation_error'.($this->piVars['error']));
				$markers['ERROR_MESSAGE'] = $this->cObj->stdWrap($errorMessage,$this->conf['errorWrap.']);
			}
			else {
				$markers['ERROR_MESSAGE'] = '';
			}

// Display donation form

			$markers['FORM_URL'] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
			$markers['FORM_NAME'] = $this->conf['formName'];
			$markers['PAYMETHODS'] = $options;
			if (empty($this->conf['donateView.']['projectTitle.'])) {
				$markers['PROJECT_TITLE'] = '';
			}
			else {
				$markers['PROJECT_TITLE'] = $this->cObj->stdWrap('',$this->conf['donateView.']['projectTitle.']);
			}
			if (empty($uid)) {
				$markers['AMOUNT_MIN'] = '';
				$markers['AMOUNT_MAX'] = $currency['cu_iso_3'];
			}
			else {
				$formattedValue = $this->moneylibObj->format(intval($row['min_payment'] * $currency['cu_sub_divisor']), $currency['cu_iso_3'], true);
				if (empty($this->conf['donateView.']['amountMin.'])) {
					$markers['AMOUNT_MIN'] = $formattedValue;
				}
				else {
					$markers['AMOUNT_MIN'] = $this->cObj->stdWrap($formattedValue,$this->conf['donateView.']['amountMin.']);
				}
				$formattedValue = $this->moneylibObj->format(intval(($row['amount'] - $row['paid']) * $currency['cu_sub_divisor']), $currency['cu_iso_3'], true);
				if (empty($this->conf['donateView.']['amountMax.'])) {
					$markers['AMOUNT_MAX'] = $formattedValue;
				}
				else {
					$markers['AMOUNT_MAX'] = $this->cObj->stdWrap($formattedValue,$this->conf['donateView.']['amountMax.']);
				}
			}
			$amount = $this->getPiVars('amount');
			$markers['AMOUNT_VAL'] = (empty($amount)) ? '' : $amount;
			if (isset($currency)) $markers['CURRENCY'] = $currency['cu_iso_3'];
			
			$markers['HIDDEN_FIELDS'] = '<input type="hidden" name="tx_donations[view]" value="confirm" />';
			if (isset($row['uid'])) {
				$markers['HIDDEN_FIELDS'] .= '<input type="hidden" name="tx_donations[project_uid]" value="'.$row['uid'].'" />';
			}
			$markers['COMPANY_VAL'] = $company = $this->getPiVars('company', true);
			$markers['NAME_VAL'] = $name = $this->getPiVars('name', true);
			$markers['ADDR_VAL'] = $addr = $this->getPiVars('addr', true);
			$markers['ZIP_VAL'] = $zip = $this->getPiVars('zip', true);
			$markers['CITY_VAL'] = $city = $this->getPiVars('city', true);
			$markers['COUNTRY_VAL'] = $country = $this->getPiVars('country', true);
			$markers['EMAIL_VAL'] = $email = $this->getPiVars('email', true);
//			$markers['BUTTONS'] = '<input type="submit" name="tx_donations[confirm]" value="'.($this->pi_getLL('continue')).'" />';
			$markers['BUTTONS'] = '<input type="submit" name="submit" value="'.($this->pi_getLL('continue')).'" />';
			if (empty($uid)) {
				$markers['BACK_LINK'] = '';
			}
			else {
				$markers['BACK_LINK'] = $this->pi_linkTP($this->pi_getLL('back_list_link'), array());
			}
			return $this->substituteMarkerArray($subpart, $markers, '###|###');
		}
		else {
			return $this->cObj->stdWrap($this->pi_getLL('payment_methods_error'),$this->conf['errorWrap.']);
		}
	}

	/**
	 * This method displays a confirmation screen before submitting the payment
	 *
	 * @param integer	uid of the project to donate to
	 * @return The content to be displayed
	 */
	protected function confirmView($uid) {

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
						// less than the minimum if a minimum is configured
					if ($rawAmount < $row['min_payment'] && $row['min_payment'] > 0) {
						$errorCode = 3;
					}
						// more than the maximum if a maximum is configured
					elseif (($rawAmount > $row['amount'] - $row['paid']) && $row['amount'] > 0) {
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
			$markers['ERROR_MESSAGE'] = $this->cObj->stdWrap($message,$this->conf['errorWrap.']);
		}
		else {
			$markers['ERROR_MESSAGE'] = '';
		}
		$markers['FORM_URL'] = $providerObj->transaction_formGetActionURI();
		$markers['FORM_NAME'] = $this->conf['formName'];
		$markers['FORM_FORM_PARAMS'] = $providerObj->transaction_formGetFormParms();
		$backURL = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', array('tx_donations[view]' => 'donate'));
//		$markers['BUTTONS'] = '<input type="submit" id="backButton" name="tx_donations[donate]" value="'.$this->pi_getLL('back').'" onclick="this.form.action=\''.$backURL.'\'" disabled="true" />';
		$markers['BUTTONS'] = '<input type="submit" id="backButton" name="back" value="'.$this->pi_getLL('back').'" onclick="this.form.action=\''.$backURL.'\'" disabled="true" />';
		$markers['BUTTONS'] .= '&nbsp;<input type="submit" name="submit" value="'.$this->pi_getLL('confirm').'" '.($providerObj->transaction_formGetSubmitParms()).' />';
		$markers['BUTTONS'] .= '<script>document.forms["donateForm"].backButton.disabled=false;</script><noscript><p>'.$this->pi_getLL('no_javascript_message').'</p></noscript>';
		if (empty($this->conf['confirmView.']['projectTitle.'])) {
			$markers['PROJECT_TITLE'] = '';
		}
		else {
			$markers['PROJECT_TITLE'] = $this->cObj->stdWrap('',$this->conf['confirmView.']['projectTitle.']);
		}

// Display input data for confirmation

		$markers['PAYMETHOD'] = $GLOBALS['TSFE']->sL($paymethodLabel);
		$markers['AMOUNT_VAL'] = $this->moneylibObj->format($amount, $currency['cu_iso_3'], false);
		$markers['CURRENCY'] = $currency['cu_iso_3'];

		$markers['PAYMENT_DETAILS'] = implode('', $visibleFields);

		$markers['COMPANY_VAL'] = $this->getPiVars('company', true);
		$markers['NAME_VAL'] = $this->getPiVars('name', true);
		$markers['ADDR_VAL'] = $this->getPiVars('addr', true);
		$markers['ZIP_VAL'] = $this->getPiVars('zip', true);
		$markers['CITY_VAL'] = $this->getPiVars('city', true);
		$markers['COUNTRY_VAL'] = $this->getPiVars('country', true);
		$markers['EMAIL_VAL'] = $this->getPiVars('email', true);
		$markers['HIDDEN_FIELDS'] = implode("\n", $hiddenFields);
		return $this->substituteMarkerArray($subpart, $markers, '###|###');
	}

	/**
	 * This method displays the receipt screen when a payment has successfully been processed
	 *
	 * @param integer	uid of the project to donate to
	 * @return The content to be displayed
	 */
	protected function receiptView($uid) {
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
			$fields['pid'] = $this->conf['depositsPID'];
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

		if (empty($this->conf['receiptView.']['projectTitle.'])) {
			$markers['PROJECT_TITLE'] = '';
		}
		else {
			$markers['PROJECT_TITLE'] = $this->cObj->stdWrap('',$this->conf['receiptView.']['projectTitle.']);
		}
		$markers['MESSAGE'] = $this->cObj->cObjGetSingle($this->conf['thankMessage'], $this->conf['thankMessage.'], 'thankMessage');

		$markers['PAYMENT_REFERENCE'] = $paymentReference;
		$markers['PAYMETHOD'] = $GLOBALS['TSFE']->sL($paymethodLabel);
		$markers['AMOUNT_VAL'] = $this->moneylibObj->format($amount * $currency['cu_sub_divisor'], $currency['cu_iso_3'], false);
		$markers['CURRENCY'] = $currency['cu_iso_3'];

		$markers['COMPANY_VAL'] = $this->getPiVars('company');
		$markers['NAME_VAL'] = $this->getPiVars('name');
		$markers['ADDR_VAL'] = $this->getPiVars('addr');
		$markers['ZIP_VAL'] = $this->getPiVars('zip');
		$markers['CITY_VAL'] = $this->getPiVars('city');
		$markers['COUNTRY_VAL'] = $this->getPiVars('country');
		$markers['EMAIL_VAL'] = $this->getPiVars('email');

// Prepare markers for confirmation mails, if necessary

		if (!empty($this->conf['mail.']['sendToAdmin']) || !empty($this->conf['mail.']['sendToUser'])) {
			require_once(PATH_t3lib.'class.t3lib_htmlmail.php');
			$mailMarkers = $markers;
			$mailMarkers['PROJECT_TITLE'] = (empty($uid)) ? '-' : $row['title'];
			$mailMarkers['DATE_VAL'] = $this->cObj->stdWrap(time(),$this->conf['mail.']['dateWrap.']);

// Send mail to admin

			if (!empty($this->conf['mail.']['sendToAdmin'])) {
				$mailMarkers['MESSAGE'] = $this->conf['mail.']['adminMessage'];
				$mailSubpart = $this->cObj->getSubpart($this->template, '###ADMINMAIL###');
				$mailText = $this->substituteMarkerArray($mailSubpart, $mailMarkers, '###|###');
				$adminMailObj = t3lib_div::makeInstance('t3lib_htmlmail');
				$adminMailObj->start();
				$adminMailObj->subject = $this->conf['mail.']['adminSubject'];
				$adminMailObj->from_email = $this->conf['mail.']['senderMail'];
				$adminMailObj->from_name = $this->conf['mail.']['senderName'];
				$adminMailObj->replyto_email = $this->conf['mail.']['senderMail'];
				$adminMailObj->replyto_name = $this->conf['mail.']['senderName'];
				$adminMailObj->returnPath = $this->conf['mail.']['senderMail'];
				$adminMailObj->setPlain($mailText); 
				$adminMailObj->send($this->conf['mail.']['adminMail']);	
			}

// Send mail to user

			if (!empty($this->conf['mail.']['sendToUser'])) {
				$mailMarkers['MESSAGE'] = $this->conf['mail.']['userMessage'];
				$mailSubpart = $this->cObj->getSubpart($this->template, '###USERMAIL###');
				$mailText = $this->substituteMarkerArray($mailSubpart, $mailMarkers, '###|###');
				$userMailObj = t3lib_div::makeInstance('t3lib_htmlmail');
				$userMailObj->start();
				$userMailObj->subject = $this->conf['mail.']['userSubject'];
				$userMailObj->from_email = $this->conf['mail.']['senderMail'];
				$userMailObj->from_name = $this->conf['mail.']['senderName'];
				$userMailObj->replyto_email = $this->conf['mail.']['senderMail'];
				$userMailObj->replyto_name = $this->conf['mail.']['senderName'];
				$userMailObj->returnPath = $this->conf['mail.']['senderMail'];
				$userMailObj->setPlain($mailText);
				$mailto = $this->getPiVars('email');
					// Prevent e-mail injection (code taken from th_mailformplus)
				if (strstr($mailto, '@') && !eregi("\r",$mailto) && !eregi("\n",$mailto)) {
					$userMailObj->send($mailto);	
				}
			}
		}

		return $this->substituteMarkerArray($subpart, $markers, '###|###');
	}

	/**
	 * This method returns a piVar first making sure that it is defined and not false, not 0 and not an empty string
	 * It can also call htmlspecialchars() on the piVar
	 *
	 * @param 	string		$name: the name of the variable to fetch
	 * @param	boolean		$hsc: true to use htmlspecialchars() on the value
	 * @return	mixed		The value of the variable or an empty string if empty
	 */
	protected function getPiVars($name, $hsc = false) {
		return empty($this->piVars[$name]) ? '' : ($hsc ? htmlspecialchars($this->piVars[$name]): $this->piVars[$name]);
	}

	/**
	 * This method returns the database record for the project that is being donated to
	 *
	 * @param integer	uid of the project
	 * @return Associative array of the corresponding record (or empty array if record not found)
	 */
	protected function getProject($uid) {
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
	protected function getCurrency($uid) {
		$currency = array();
		if (empty($uid)) { // If no uid is available try to get the default currency (as defined in TS setup)
			if (!empty($this->conf['defaultCurrency'])) {
				$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','static_currencies', "cu_iso_3 = '".$this->conf['defaultCurrency']."'");
				if ($result) $currency = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
			}
		}
		else {
			$currency = $this->pi_getRecord('static_currencies', $uid);
		}
		return $currency;
	}

	/**
     * This method performs various initialisations
     *
     * @param		array		$conf: TypoScript configuration
     *
     * @return		mixed		Returns an array with error messages if errors detected, otherwise boolean false
     */
    protected function init($conf) {
    		// General initialisation
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1; // Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		$confErrMsgs = array();

			// Clean up all the piVars for possible XSS code
		$this->piVars = $this->cleanUpValues($this->piVars);

			// Get an instance of the moneylib object
		$this->moneylibObj = t3lib_div::makeInstance('tx_moneylib');

	        // Get template and issue error if empty
		$this->template = trim($this->cObj->fileResource($this->conf['template']));
		if (empty($this->template)) {
			$confErrMsgs[] = $this->pi_getLL('no_template');
		}
         
		return count($confErrMsgs) >= 0 ? $confErrMsgs : false;
	}

	/**
	 * This method recursively cleans up the an array of from any HTML tags that may have been planted there
	 *
	 * @param		array	$allValues: key-value pairs of values to clean up
	 * @return		void
	 */
	protected function cleanUpValues($allValues) {
		foreach ($allValues as $key => $value) {
			if (is_array($value)) {
				$allValues[$key] = $this->cleanUpValues($value);
			}
			else {
				$allValues[$key] = strip_tags($value);
			}
		}
		return $allValues;
	}

	/**
	 * This method is a wrapper for tslib_cobj::substituteMarkerArray(), which has been replaced
	 * by t3lib_parsehtml::substituteMarkerArray() as of TYPO3 4.2. It avoids repeating the version
	 * compatibility test dozens of times in the class (tslib_cobj::substituteMarkerArray() still exists in 4.2,
	 * but it's behavior is slightly different)
	 *
	 * @param	string		The content stream, typically HTML template content.
	 * @param	array		The array of key/value pairs being marker/content values used in the substitution. For each element in this array the function will substitute a marker in the content stream with the content.
	 * @param	string		A wrap value - [part 1] | [part 2] - for the markers before substitution
	 * @param	boolean		If set, all marker string substitution is done with upper-case markers.
	 *
	 * @return	string		The processed output stream
	 */
	protected function substituteMarkerArray($content, $markerArray, $wrap = '', $uppercase = false) {
		if (t3lib_div::compat_version('4.2')) { // Call the newer method, but force unreplaced markers to be deleted
			return t3lib_parsehtml::substituteMarkerArray($content, $markerArray, $wrap, $uppercase, true);
		}
		else {
			return $this->cObj->substituteMarkerArray($content, $markerArray, $wrap, $uppercase);
		}
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/donations/pi1/class.tx_donations_pi1.php'])	{
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/donations/pi1/class.tx_donations_pi1.php']);
}

?>
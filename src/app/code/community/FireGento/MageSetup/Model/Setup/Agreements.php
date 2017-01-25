<?php
/**
 * This file is part of a FireGento e.V. module.
 *
 * This FireGento e.V. module is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 3 as
 * published by the Free Software Foundation.
 *
 * This script is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * PHP version 5
 *
 * @category  FireGento
 * @package   FireGento_MageSetup
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2013-2015 FireGento Team (http://www.firegento.com)
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   2.2.2
 * @since     0.2.0
 */

/**
 * Setup class for Checkout Agreements
 *
 * @category FireGento
 * @package  FireGento_MageSetup
 * @author   FireGento Team <team@firegento.com>
 */
class FireGento_MageSetup_Model_Setup_Agreements extends FireGento_MageSetup_Model_Setup_Abstract
{
    /**
     * @var FireGento_MageSetup_Implementor_StoreConfig
     */
    private $storeConfig;
    /**
     * @var FireGento_MageSetup_Implementor_Directories
     */
    private $directories;
    /**
     * @var FireGento_MageSetup_Implementor_AgreementRepository
     */
    private $agreementRepository;

    /**
     * @param FireGento_MageSetup_Implementor_StoreConfig $storeConfig
     * @param FireGento_MageSetup_Implementor_Directories $directories
     * @param FireGento_MageSetup_Implementor_AgreementRepository $agreementRepository
     */
    public function __construct(FireGento_MageSetup_Implementor_StoreConfig $storeConfig, FireGento_MageSetup_Implementor_Directories $directories, FireGento_MageSetup_Implementor_AgreementRepository $agreementRepository)
    {
        $this->storeConfig = $storeConfig;
        $this->directories = $directories;
        $this->agreementRepository = $agreementRepository;
    }


    /**
     * Setup Checkout Agreements
     *
     * @param array $locale Locale options
     */
    public function setup($locale = array('default' => 'de_DE'))
    {
        foreach ($locale as $storeId => $localeCode) {

            if (!$localeCode) {
                if (sizeof($locale) == 1) {
                    continue;
                }
                $localeCode = $locale['default'];
            }

            if ($storeId == 'default') {
                if (sizeof($locale) > 1) {
                    continue;
                }
                $storeId = null;
            }

            foreach ($this->_getConfigAgreements() as $name => $data) {
                if ($data['execute'] == 1) {
                    $this->_createAgreement($data, $localeCode, false, $storeId);
                }
            }
        }

        // Set config value to true
        $this->storeConfig->saveDefaultValue('checkout/options/enable_agreements', '1');
    }

    /**
     * Collect data and create Agreement
     *
     * @param  array    $agreementData Cms page data
     * @param  string   $locale        Locale
     * @param  boolean  $override      Override cms page if it exists
     * @param  int|null $storeId       Store Id
     * @return void
     */
    protected function _createAgreement($agreementData, $locale, $override = true, $storeId = null)
    {
        if (!is_array($agreementData)) {
            return;
        }

        $filename = $this->directories->localizedTemplates($locale) . $agreementData['filename'];
        if (!file_exists($filename)) {
            return;
        }

        $templateContent = $this->getTemplateContent($filename);

        // Find name
        $name = '';
        if (preg_match('/<!--@name\s*(.*?)\s*@-->/u', $templateContent, $matches)) {
            $name = $matches[1];
            $templateContent = str_replace($matches[0], '', $templateContent);
        }

        // Find checkbox_text
        $checkboxText = '';
        if (preg_match('/<!--@checkbox_text\s*(.*?)\s*@-->/u', $templateContent, $matches)) {
            $checkboxText = $matches[1];
            $templateContent = str_replace($matches[0], '', $templateContent);
        }

        // Remove comment lines
        $templateContent = preg_replace('#\{\*.*\*\}#suU', '', $templateContent);

        $agreementData = array(
            'name'                    => $name,
            'content'                 => $templateContent,
            'checkbox_text'           => $checkboxText,
            'is_active'               => $agreementData['is_active'],
            'is_html'                 => $agreementData['is_html'],
            'is_required'             => $agreementData['is_required'],
            'agreement_type'          => $agreementData['agreement_type'],
            'revocation_product_type' => isset($agreementData['revocation_product_type']) ? $agreementData['revocation_product_type'] : '',
            'stores'                  => $storeId ? $storeId : 0,
        );

        $agreement = $this->agreementRepository->load($storeId, $agreementData['name']);

        if ($agreement->isNew() || $override) {
            $agreement->setData($agreementData);
            $this->agreementRepository->save($agreement);
        }
    }

    /**
     * Get pages/default from config file
     *
     * @return array Config agreements
     */
    protected function _getConfigAgreements()
    {
        return $this->_getConfigNode('agreements', 'default');
    }
}

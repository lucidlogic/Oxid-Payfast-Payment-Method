<?php
/**
 *    This file is part of OXID eShop Community Edition.
 *
 *    OXID eShop Community Edition is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    OXID eShop Community Edition is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with OXID eShop Community Edition.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.oxid-esales.com
 * @package   main
 * @copyright (C) OXID eSales AG 2003-2012
 * @version OXID eShop CE
 * @version   SVN: $Id: theme.php 25466 2010-02-01 14:12:07Z alfonsas $
 */

/**
 * Metadata version
 */
$sMetadataVersion = '1.0';

/**
 * Module information
 */
$aModule = array(
    'id'           => 'payfast',
    'title'        => 'Payfast Payment Gateway',
    'description'  => 'Module to integrate Payfast as a payment method.',
    'thumbnail'    => 'picture.png',
    'version'      => '1.0',
    'email'        => 'gareth@lucidlogic.co.za',
     'url'        => 'http://lucidlogic.co.za',
    'author'       => 'LucidLogic',
    'settings' => array(
        array('group' => 'main', 'name' => 'api_url', 'type' => 'str',  'value' => 'https://www.payfast.co.za/eng/process'),
        array('group' => 'main', 'name' => 'merchant_id', 'type' => 'str',  'value' => '10183206'),
         array('group' => 'main', 'name' => 'merchant_key', 'type' => 'str',  'value' => 'uxne16o1b7pdt'),
       
    ),
    'extend'       => array(
        'oxorder' => 'payfast/payfast'
    )
);
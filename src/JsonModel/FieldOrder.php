<?php
/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2018 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for technical reasons, the Appropriate Legal Notices must
 * display the words "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */
namespace Jsonizer\JsonModel;

class FieldOrder
{
    /**
     * Edit this array to define an order for fields in which they should appear in views
     *
     * @var array
     */
    static $order = [
        'name',
    ];

    /**
     * @var array
     */
    static $unordered = [
    ];

    /**
     * @param $fieldName
     * @return false|int|string
     */
    public static function getFieldOrder($fieldName)
    {
        $fieldName = strtolower($fieldName);
        $key = array_search($fieldName, static::$order, false);
        if ($key === false) {
            if (strpos($fieldName, '_start') !==  false) {
                return static::getFieldOrder(str_replace('_start', '', $fieldName)) + 1;
            }
            if (strpos($fieldName, '_end') !==  false) {
                return static::getFieldOrder(str_replace('_end', '_start', $fieldName)) + 1;
            }
            if (strpos($fieldName, '_amount') !==  false) {
                return static::getFieldOrder(str_replace('_amount', '', $fieldName)) + 1;
            }
            if (strpos($fieldName, '_unit') !==  false) {
                return static::getFieldOrder(str_replace('_unit', '_amount', $fieldName)) + 1;
            }
            static::$unordered[] = $fieldName;
            return md5($fieldName);
        }
        return $key * 10;
    }

    /**
     *
     */
    public static function outputUnordered()
    {
        $unique = array_unique(static::$unordered);
        $count = count($unique);
        if (!$count) {
            return;
        }
        foreach ($unique as $fieldName) {
            echo "'$fieldName'," . PHP_EOL;
        }
        $fileName = __FILE__;
        echo "Total: $count fields unordered (Edit $fileName to order these fields)" . PHP_EOL;
    }
}
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

namespace Jsonizer\OutputModel;


class OutputModel
{
    private $content;

    private $indentation = 0;

    public function __construct()
    {
        $this->content = '';
        $this->add('<?php');
        $this->endLine();
        $this->add('// WARNING: The contents of this file are auto-generated from Jsonizer/OutputModel');
        $this->endLine();
        $this->endLine();
    }

    /**
     * @param string $name
     * @param string|array $key
     * @param mixed $value
     */
    public function addArrayValue($name, $key, $value)
    {
        $keyVal = '';
        if (!is_array($key)) {
            $key = [$key];
        }
        foreach ($key as $keyItem) {
            $keyVal .= '[';
            $keyVal .= $keyItem === false ? '' : "'{$keyItem}'";
            $keyVal .= ']';
        }
        $line = '$' . $name . $keyVal . ' = ';
        $this->add($line);
        $this->addVariable($value);
        $this->add(';');
        $this->endLine();
    }

    /**
     * @param $path
     */
    public function addInclude($path)
    {
        $line = 'include("';
        $line .= $path;
        $line .= '");';
        $this->add($line);
        $this->endLine();
        $this->endLine();

    }

    /**
     * @param $array
     * @return bool
     */
    protected function hasOnlyNumericKeys($array)
    {
        $count = 0;
        foreach ($array as $key => $val) {
            if ($key !== $count) {
                return false;
            }
            $count++;
        }
        return true;
    }

    /**
     * @param mixed $var
     */
    public function addVariable($var)
    {
        if (is_array($var)) {
            $this->add('[');
            $this->indentation += 4;
            $this->endLine();
            $onlyNumericKeys = $this->hasOnlyNumericKeys($var);
            foreach ($var as $key => $value) {
                if (!$onlyNumericKeys) {
                    $this->add("'" . $key . "' => ");
                }
                $this->addVariable($value);
                $this->add(',');
                $this->endLine();
            }
            $this->removeIndentationLevel();
            $this->indentation -= 4;
            $this->add(']');
            return;
        }
        if (is_bool($var)) {
            $this->add($var ? 'true' : 'false');
            return;
        }
        $this->add("'" . $var . "'");
    }

    /**
     *
     */
    protected function removeIndentationLevel()
    {
        $this->content = substr($this->content, 0, -4);
    }

    /**
     *
     */
    public function endLine()
    {
        $this->add(PHP_EOL);
        $this->add(str_repeat(' ', $this->indentation));
    }

    /**
     * @param string $value
     */
    public function add($value)
    {
        $this->content .= $value;
    }

    /**
     * @param $path
     */
    public function writeToLocation($path)
    {
        $this->createSubDirectories($path);
        file_put_contents($path, $this->content);
    }

    /**
     * @return string
     */
    public function writeToString()
    {
        return $this->content;
    }

    /**
     * @param $fullPath
     */
    private function createSubDirectories($fullPath)
    {
        $folder = dirname($fullPath);
        $directories = explode('/', $folder);
        $path = '';
        foreach ($directories as $directory) {
            if ($directory === '') {
                continue;
            }
            $path .= '/' . $directory;
            !is_dir($path) && !mkdir($path) && !is_dir($path);
        }
    }
}

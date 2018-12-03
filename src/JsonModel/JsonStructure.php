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


class JsonStructure
{
    /**
     * @var array
     */
    public $modules;

    /**
     * @var array
     */
    public $relationships;

    /**
     * @var JsonModel
     */
    protected $model;

    /**
     * @var string
     */
    protected $baseDir;

    /**
     *
     */
    public function load()
    {
        $this->model = new JsonModel();
        $this->modules = [];
        $this->baseDir = __DIR__ . '/../../raw/';

        $this->loadModules();
        $this->loadFields();
        $this->loadRelationships();
    }

    /**
     *
     */
    protected function loadModules()
    {
        $modules = $this->model->getFolderJsonFiles($this->baseDir . 'modules');
        foreach ($modules as $moduleName) {
            $module = json_decode(file_get_contents($this->baseDir . 'modules/' . $moduleName . '.json'));
            $module->fields = [];
            $module->relationships = [];
            $this->modules[$module->name] = $module;
        }
    }

    /**
     *
     */
    protected function loadFields()
    {
        $fieldsDir = $this->baseDir . 'fields/';
        foreach ($this->modules as $key => $module) {
            $fields = $this->model->getFolderJsonFiles($fieldsDir . $module->name);
            foreach ($fields as $fieldName) {
                $fileName = $fieldsDir . $module->name . "/$fieldName.json";
                $field = json_decode(file_get_contents($fileName));
                $module->fields[] = $field;
                $this->modules[$key] = $module;
            }
        }
    }

    /**
     *
     */
    protected function loadRelationships()
    {
        $relationshipsDir = $this->baseDir . 'relationships/';
        $relationshipNames = $this->model->getFolderJsonFiles($relationshipsDir);
        foreach ($relationshipNames as $relationshipName) {
            $fileName = $relationshipsDir . "/$relationshipName.json";
            $relationship = json_decode(file_get_contents($fileName));
            if (!isset($this->modules[$relationship->leftModule]) || !isset($this->modules[$relationship->rightModule])) {
                continue;
            }
            $this->modules[$relationship->leftModule]->relationships[] = $relationship;
            $this->modules[$relationship->rightModule]->relationships[] = $relationship;
            $this->relationships[$relationship->identifier] = $relationship;
        }
    }
}
<?php

namespace Jsonizer\Command;

use Jsonizer\JsonModel\FieldOrder;
use Jsonizer\JsonModel\JsonStructure;
use Jsonizer\OutputModel\OutputModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildJsonCommand extends Command
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var
     */
    protected $helper;

    /**
     * @var JsonStructure
     */
    protected $structure;

    /**
     * @var
     */
    protected $file;

    /**
     * @var
     */
    protected $sample_dir;

    /**
     * @var
     */
    protected $suite_dir;

    /**
     * @var
     */
    protected $currentModule;

    /**
     * @var
     */
    protected $currentModuleName;

    /**
     * @var
     */
    protected $currentFile;

    /**
     * @var
     */
    protected $currentFilePath;

    /**
     * @var
     */
    protected $basicModule = 'BasicModule';

    /**
     * @var
     */
    protected $basicModuleLabel = 'Basic Module';

    /**
     * @var int
     */
    const MAX_FILES = 99999;

    /**
     * @var array
     */
    protected $nonDefFiles = [
        'BasicModule.php',
        'Menu.php',
        'en_us.lang.php',
        'vardefs.php',
    ];

    protected $imageFileExt = [
        'gif',
        'svg',
    ];

    const APP_DIR = true;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->sample_dir = __DIR__ . '/../../sample/Basic';
        $this->suite_dir = __DIR__ . '/../../../../..';

        $this
            ->setName('build')
            ->setDescription('Builds SuiteCRM modules, fields and relationships from the json structure.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->input = $input;
        $this->structure = new JsonStructure();

        $this->structure->load();
        $this->preProcess();
        try {
            $this->buildModules();
            $this->setUpCollectiveBeanFiles();
            $this->createRelationshipFiles();
        }
        catch (\Exception $e) {
            $this->output->writeln($e->getMessage());
        }

        return 1;
    }

    /**
     *
     */
    protected function preProcess()
    {
        foreach ($this->structure->modules as &$module) {
            $module->fields = $this->preprocessFields($module->fields);
            foreach ($module->relationships as &$moduleRelationship) {
                $this->preProcessRelationship($moduleRelationship);
            }
            unset($moduleRelationship);
        }
        unset($module);
        foreach ($this->structure->relationships as &$relationship) {
            $this->preProcessRelationship($relationship);
        }
        unset($relationship);
        FieldOrder::outputUnordered();
    }

    /**
     * @param array $fieldArr
     * @return array
     */
    protected function preprocessFields($fieldArr)
    {
        $fields = [];
        foreach ($fieldArr as $field) {
            $key = FieldOrder::getFieldOrder($field->name);
            $field->name = 'sa_' . $field->name;
            $field->labelKey = 'LBL_' . strtoupper($field->name);
            $fields[$key] = $field;
        }
        ksort($fields);
        return array_values($fields);
    }

    /**
     * @param \stdClass $relationship
     */
    protected function preProcessRelationship(\stdClass &$relationship)
    {
        if (!isset($relationship->identifier)) {
            return;
        }
        if ($relationship->type === 'many-to-one') {
            $relationship->type = 'one-to-many';
            $left = $relationship->leftModule;
            $leftLabel = $relationship->leftLabel;
            $leftAlias = $relationship->leftAlias;
            $right = $relationship->rightModule;
            $rightLabel = $relationship->rightLabel;
            $rightAlias = $relationship->rightAlias;
            $relationship->leftModule = $right;
            $relationship->leftLabel = $rightLabel;
            $relationship->leftAlias = $rightAlias;
            $relationship->rightModule = $left;
            $relationship->rightLabel = $leftLabel;
            $relationship->rightAlias = $leftAlias;
        }
        $relationship->suiteLeft = $this->getSuiteModuleName($relationship->leftModule);
        $relationship->suiteRight = $this->getSuiteModuleName($relationship->rightModule);
        $suiteBase = strtolower($relationship->suiteLeft . '_' . $relationship->suiteRight);
        $relationship->suiteIdentifier = $suiteBase . '_' . substr($relationship->identifier, -1);
        $relationship->leftLabelKey = strtoupper('LBL_' . $relationship->suiteIdentifier . '_FROM_' . $relationship->suiteLeft);
        $relationship->rightLabelKey = strtoupper('LBL_' . $relationship->suiteIdentifier . '_FROM_' . $relationship->suiteRight);
        $tableIdentifier = $this->ensureStringLengthOrTruncate($relationship->suiteIdentifier);
        $relationship->leftKey = $tableIdentifier . '_ida';
        $relationship->rightKey = $tableIdentifier . '_idb';
        $relationship->tableIdentifier = $tableIdentifier . '_c';
        unset($relationship->identifier);
    }

    /**
     * @param $str
     * @return string
     */
    protected function ensureStringLengthOrTruncate($str)
    {
        if (strlen($str) < 40) {
            return $str;
        }
        return substr($str, 0, 35) . substr(md5($str), 0, 5);
    }

    /**
     *
     */
    protected function buildModules()
    {
        $count = 0;
        foreach ($this->structure->modules as $module) {
            // build the module
            $this->currentModule = $module;
            $this->currentModuleName = $this->getSuiteModuleName($module->name);
            $this->copyBasicModuleFiles();
            $this->createVardefAndLang();

            $count++;
            // temporary cap
            if ($count >= self::MAX_FILES) {
                return;
            }
        }
    }

    /**
     * @param $name
     * @return string
     */
    protected function getSuiteModuleName($name)
    {
        return 'SA_' . $name;
    }

    /**
     *
     */
    protected function createVardefAndLang()
    {
        $vardefs = new OutputModel();
        $lang = new OutputModel();
        foreach ($this->currentModule->fields as $field) {

            if ($field->name === 'sa_id') {
                continue;
            }
            $lang->addArrayValue('mod_strings', $field->labelKey, $field->label);

            $fieldArr = (array)$field;
            unset($fieldArr['source']);
            unset($fieldArr['module']);
            $fieldArr['len'] = $fieldArr['length'];
            unset($fieldArr['length']);
            $fieldArr['vname'] = $fieldArr['labelKey'];
            unset($fieldArr['labelKey']);
            if (!in_array($fieldArr['type'], ['currency', 'float', 'decimal'])) {
                unset($fieldArr['precision']);
            }
            $vardefs->addArrayValue('dictionary', [$this->currentModuleName, 'fields', $fieldArr['name']], $fieldArr);
            $vardefs->endLine();
        }
        $vardefs->writeToLocation($this->getExtensionDir() . "Vardefs/Jsonizer_Fields.php");
        $lang->writeToLocation($this->getExtensionDir() . "Language/en_us.Jsonizer_Fields.php");
    }

    /**
     * @param bool|string $app
     * @return string
     */
    protected function getExtensionDir($app = false)
    {
        if ($app === self::APP_DIR) {
            return $this->suite_dir . "/custom/Extension/application/Ext/";
        }
        if (is_string($app)) {
            return $this->suite_dir . "/custom/Extension/modules/{$app}/Ext/";
        }
        return $this->suite_dir . "/custom/Extension/modules/{$this->currentModuleName}/Ext/";
    }

    /**
     *
     */
    protected function createRelationshipFiles()
    {
        $count = 0;
        foreach ($this->structure->relationships as $relationship) {
            $this->addRelationshipTableDictionary($relationship);
            $this->addRelationshipLangFiles($relationship);
            $this->addRelationshipSubPanelDefs($relationship);
            $this->addRelationshipVarDefs($relationship);
            $this->addRelationshipMetaDataFile($relationship);

            $count++;
            // temporary cap
            if ($count >= self::MAX_FILES) {
                return;
            }
        }
    }

    /**
     * @param \stdClass $relationship
     */
    protected function addRelationshipMetaDataFile(\stdClass $relationship)
    {
        $metaDataFile = new OutputModel();
        $metaDataFile->addArrayValue('dictionary', $relationship->suiteIdentifier, $this->getMetaDataSampleFile($relationship));
        $metaDataFile->writeToLocation($this->suite_dir . "/custom/metadata/{$relationship->suiteIdentifier}MetaData.php");
    }

    /**
     * @param \stdClass $relationship
     * @return array
     */
    protected function getMetaDataSampleFile(\stdClass $relationship)
    {
        return [
            'true_relationship_type' => $relationship->type,
            'relationships' => [
                $relationship->suiteIdentifier => [
                    'lhs_module' => $relationship->suiteLeft,
                    'lhs_table' => strtolower($relationship->suiteLeft),
                    'lhs_key' => 'id',
                    'rhs_module' => $relationship->suiteRight,
                    'rhs_table' => strtolower($relationship->suiteRight),
                    'rhs_key' => 'id',
                    'relationship_type' => 'many-to-many',
                    'join_table' => $relationship->tableIdentifier,
                    'join_key_lhs' => $relationship->leftKey,
                    'join_key_rhs' => $relationship->rightKey,
                ],
            ],
            'table' => $relationship->tableIdentifier,
            'fields' => [
                [
                    'name' => 'id',
                    'type' => 'varchar',
                    'len' => 36,
                ],
                [
                    'name' => 'date_modified',
                    'type' => 'datetime',
                ],
                [
                    'name' => 'deleted',
                    'type' => 'bool',
                    'len' => '1',
                    'default' => '0',
                    'required' => true,
                ],
                [
                    'name' => $relationship->leftKey,
                    'type' => 'varchar',
                    'len' => 36,
                ],
                [
                    'name' => $relationship->rightKey,
                    'type' => 'varchar',
                    'len' => 36,
                ],
            ],
            'indices' => [
                [
                    'name' => $relationship->tableIdentifier . 'spk',
                    'type' => 'primary',
                    'fields' => [0 => 'id',],
                ],
                [
                    'name' => $relationship->tableIdentifier . '_alt',
                    'type' => 'alternate_key',
                    'fields' => [
                        0 => $relationship->leftKey,
                        1 => $relationship->rightKey,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param \stdClass $relationship
     */
    protected function addRelationshipVarDefs(\stdClass $relationship)
    {
        $leftVardef = new OutputModel();
        $data = $this->getRelationshipVardefSample($relationship, true);
        $leftVardef->addArrayValue('dictionary', [$relationship->suiteLeft, 'fields', $relationship->suiteIdentifier], $data['base_field']);
        if (in_array($relationship->type, ['one-to-one', 'many-to-one'])) {
            $leftVardef->addArrayValue('dictionary', [$relationship->suiteLeft, 'fields', $data['name_field']['name']], $data['name_field']);
            $leftVardef->addArrayValue('dictionary', [$relationship->suiteLeft, 'fields', $data['id_field']['name']], $data['id_field']);
        }
        $leftVardef->writeToLocation($this->getExtensionDir($relationship->suiteLeft) . "Vardefs/{$relationship->suiteIdentifier}{$relationship->suiteLeft}.php");

        $rightVardef = new OutputModel();
        $data = $this->getRelationshipVardefSample($relationship, false);
        $rightVardef->addArrayValue('dictionary', [$relationship->suiteRight, 'fields', $relationship->suiteIdentifier], $data['base_field']);
        if (in_array($relationship->type, ['one-to-one', 'one-to-many'])) {
            $rightVardef->addArrayValue('dictionary', [$relationship->suiteRight, 'fields', $data['name_field']['name']], $data['name_field']);
            $rightVardef->addArrayValue('dictionary', [$relationship->suiteRight, 'fields', $data['id_field']['name']], $data['id_field']);
        }
        $rightVardef->writeToLocation($this->getExtensionDir($relationship->suiteRight) . "Vardefs/{$relationship->suiteIdentifier}{$relationship->suiteRight}.php");
    }

    /**
     * @param \stdClass $relationship
     * @param bool $isLeftSide
     * @return array
     */
    protected function getRelationshipVardefSample(\stdClass $relationship, $isLeftSide)
    {
        $idName = $isLeftSide ? $relationship->rightKey : $relationship->leftKey;
        $module = $isLeftSide ? $relationship->suiteRight : $relationship->suiteLeft;
        $vName = $isLeftSide ? $relationship->rightLabelKey : $relationship->leftLabelKey;
        $data = [
            'base_field' => [
                'name' => $relationship->suiteIdentifier,
                'type' => 'link',
                'relationship' => $relationship->suiteIdentifier,
                'source' => 'non-db',
                'module' => $module,
                'bean_name' => false,
                'vname' => $vName,
                'id_name' => $idName,

            ],
            'name_field' => [
                'name' => $relationship->suiteIdentifier . '_name',
                'type' => 'relate',
                'source' => 'non-db',
                'vname' => $vName,
                'save' => true,
                'id_name' => $idName,
                'link' => $relationship->suiteIdentifier,
                'table' => strtolower($module),
                'module' => $module,
                'rname' => 'name',
            ],
            'id_field' => [
                'name' => $idName,
                'type' => 'link',
                'relationship' => $relationship->suiteIdentifier,
                'source' => 'non-db',
                'reportable' => false,
                'side' => $isLeftSide ? 'left' : 'right',
                'vname' => $vName,

            ],
        ];
        return $data;
    }

    /**
     * @param \stdClass $relationship
     */
    protected function addRelationshipSubPanelDefs(\stdClass $relationship)
    {
        if (in_array($relationship->type, ['many-to-many', 'one-to-many'])) {
            $leftSubPanel = new OutputModel();
            $data = $this->getRelationshipSubPanelSample();
            $data['module'] = $relationship->suiteRight;
            $data['title_key'] = $relationship->rightLabelKey;
            $data['get_subpanel_data'] = $relationship->suiteIdentifier;
            $leftSubPanel->addArrayValue('layout_defs', [$relationship->suiteLeft, 'subpanel_setup', $relationship->suiteIdentifier], $data);
            $leftSubPanel->writeToLocation($this->getExtensionDir($relationship->suiteLeft) . "Layoutdefs/{$relationship->suiteIdentifier}.php");

        }
        if (in_array($relationship->type, ['many-to-many', 'many-to-one'])) {
            $rightSubPanel = new OutputModel();
            $data = $this->getRelationshipSubPanelSample();
            $data['module'] = $relationship->suiteLeft;
            $data['title_key'] = $relationship->leftLabelKey;
            $data['get_subpanel_data'] = $relationship->suiteIdentifier;
            $rightSubPanel->addArrayValue('layout_defs', [$relationship->suiteRight, 'subpanel_setup', $relationship->suiteIdentifier], $data);
            $rightSubPanel->writeToLocation($this->getExtensionDir($relationship->suiteRight) . "Layoutdefs/{$relationship->suiteIdentifier}.php");
        }
    }

    /**
     * @return array
     */
    protected function getRelationshipSubPanelSample()
    {
        return [
            'order' => 100,
            'module' => 'SA_ExampleModule',
            'subpanel_name' => 'default',
            'sort_order' => 'asc',
            'sort_by' => 'id',
            'title_key' => 'LBL_SA_EXAMPLEMODULE_LEADS_FROM_SA_EXAMPLEMODULE_TITLE',
            'get_subpanel_data' => 'sa_examplemodule_leads',
            'top_buttons' => [
                ['widget_class' => 'SubPanelTopButtonQuickCreate',],
                ['widget_class' => 'SubPanelTopSelectButton', 'mode' => 'MultiSelect',],
            ],

        ];
    }

    /**
     * @param \stdClass $relationship
     */
    protected function addRelationshipTableDictionary($relationship)
    {
        $tableDictionary = new OutputModel();
        $requirePath = "custom/metadata/{$relationship->suiteIdentifier}MetaData.php";
        $tableDictionary->addInclude($requirePath);
        $tableDictionary->writeToLocation($this->getExtensionDir(self::APP_DIR) . "TableDictionary/{$relationship->suiteIdentifier}.php");
    }

    /**
     * @param \stdClass $relationship
     */
    protected function addRelationshipLangFiles($relationship)
    {
        $leftLang = new OutputModel();
        $leftLang->addArrayValue('mod_strings', $relationship->rightLabelKey, $relationship->rightLabel);
        $leftLang->writeToLocation($this->getExtensionDir($relationship->suiteLeft) . "Language/en_us.{$relationship->suiteIdentifier}.php");

        $rightLang = new OutputModel();
        $rightLang->addArrayValue('mod_strings', $relationship->leftLabelKey, $relationship->leftLabel);
        $rightLang->writeToLocation($this->getExtensionDir($relationship->suiteRight) . "Language/en_us.{$relationship->suiteIdentifier}.php");
    }

    /**
     *
     */
    protected function copyBasicModuleFiles()
    {
        $this->copyFilesRecursive($this->sample_dir, $this->suite_dir . '/modules/' . $this->currentModuleName);
    }

    /**
     *
     */
    protected function setUpCollectiveBeanFiles()
    {
        $beanListFile = new OutputModel();
        $beanLangFile = new OutputModel();
        foreach ($this->structure->modules as $module) {
            $this->currentModuleName = 'SA_' . $module->name;

            $classPath = "modules/{$this->currentModuleName}/{$this->currentModuleName}.php";
            $beanListFile->addArrayValue('beanList', $this->currentModuleName, $this->currentModuleName);
            $beanListFile->addArrayValue('beanFiles', $this->currentModuleName, $classPath);
            $beanListFile->addArrayValue('moduleList', false, $this->currentModuleName);
            $beanListFile->endLine();

            $beanLangFile->addArrayValue('app_list_strings', ['moduleList', $this->currentModuleName], $module->label);
            $beanLangFile->endLine();
        }

        $beanListFile->writeToLocation($this->getExtensionDir(self::APP_DIR) . "Include/Jsonizer_Modules.php");
        $beanLangFile->writeToLocation($this->getExtensionDir(self::APP_DIR) . "Language/en_us.Jsonizer_Modules.php");
    }

    /**
     * @param $inputDir
     * @param $outputDir
     */
    protected function copyFilesRecursive($inputPath, $outputPath)
    {
        $outputPath = str_replace($this->basicModule, $this->currentModuleName, $outputPath);
        if (is_dir($inputPath)) {
            !is_dir($outputPath) && !mkdir($outputPath) && !is_dir($outputPath);
            $dir = opendir($inputPath);
            while ($this->currentFile = readdir($dir)) {
                if (in_array($this->currentFile, ['.', '..'])) {
                    continue;
                }
                $this->copyFilesRecursive($inputPath . '/' . $this->currentFile, $outputPath . '/' . $this->currentFile);
            }
            return;
        }

        $this->copySingleFile($inputPath, $outputPath);
    }

    /**
     *
     */
    protected function copySingleFile($inputPath, $outputPath)
    {
        $this->currentFilePath = $inputPath;
        $updatedOutputPath = $this->modifyImageFilesOutputPath($outputPath);
        $contents = file_get_contents($inputPath);
        $fileIsAnImage = $outputPath !== $updatedOutputPath;
        if (!$fileIsAnImage) {
            $contents = $this->handleContents($contents);
        }
        file_put_contents($updatedOutputPath, $contents);
    }

    /**
     * @param $outputPath
     * @return mixed
     */
    protected function modifyImageFilesOutputPath($outputPath)
    {
        foreach ($this->imageFileExt as $fileExt) {
            if (strpos($this->currentFile, $fileExt) !==  false) {
                $moduleDir = 'modules/' . $this->currentModuleName . '/images';
                $imageDir = 'custom/themes/default/images';
                $outputPath = str_replace($moduleDir, $imageDir, $outputPath);
                $outputPath = str_replace('Module', $this->currentModuleName, $outputPath);
                break;
            }
        }
        return $outputPath;
    }

    /**
     * @param $inputPath
     * @param $contents
     * @return mixed
     */
    protected function handleContents($contents)
    {
        $processMethod = 'process_' . pathinfo($this->currentFile, PATHINFO_FILENAME);
        if (method_exists($this, $processMethod)) {
            return $this->$processMethod();
        }
        return $this->handleNonMethodFiles($contents);
    }

    /**
     * @param $contents
     * @return mixed
     */
    protected function handleNonMethodFiles($contents)
    {
        $contents = str_replace($this->basicModule, $this->currentModuleName, $contents);
        $contents = str_replace($this->basicModuleLabel, $this->currentModule->label, $contents);
        $contents = str_replace(strtolower($this->basicModule), strtolower($this->currentModuleName), $contents);
        $contents = str_replace(strtolower($this->basicModuleLabel), strtolower($this->currentModule->label), $contents);
        $contents = str_replace(strtoupper($this->basicModule), strtoupper($this->currentModuleName), $contents);
        $contents = str_replace(strtoupper($this->basicModuleLabel), strtoupper($this->currentModule->label), $contents);
        return $contents;
    }

    /**
     * @return string
     */
    protected function process_editviewdefs()
    {
        $viewdefs = [];
        require $this->currentFilePath;
        $viewdefs = $viewdefs['BasicModule'];
        $viewdefs['EditView']['panels']['default'] = $this->buildFieldViewArray('edit');
        $viewDefModel = new OutputModel();
        $viewDefModel->addArrayValue('viewdefs', $this->currentModuleName, $viewdefs);
        return $viewDefModel->writeToString();
    }

    /**
     * @return string
     */
    protected function process_quickcreatedefs()
    {
        $viewdefs = [];
        require $this->currentFilePath;
        $viewdefs = $viewdefs['BasicModule'];
        $viewdefs['QuickCreate']['panels']['default'] = $this->buildFieldViewArray('edit');
        $viewDefModel = new OutputModel();
        $viewDefModel->addArrayValue('viewdefs', $this->currentModuleName, $viewdefs);
        return $viewDefModel->writeToString();
    }

    /**
     * @return string
     */
    protected function process_detailviewdefs()
    {
        $viewdefs = [];
        require $this->currentFilePath;
        $viewdefs = $viewdefs['BasicModule'];
        $viewdefs['DetailView']['panels']['default'] = $this->buildFieldViewArray('detail');
        $viewdefs['DetailView']['panels']['default'][] = $this->getDateModifiedRow();
        $viewDefModel = new OutputModel();
        $viewDefModel->addArrayValue('viewdefs', $this->currentModuleName, $viewdefs);
        return $viewDefModel->writeToString();
    }

    /**
     * @return string
     */
    protected function process_searchdefs()
    {
        $searchdefs = [];
        require $this->currentFilePath;
        $searchdefs = $searchdefs['BasicModule'];
        $searchdefs['layout']['basic_search'][0] = 'id';
        $searchdefs['layout']['advanced_search'] = [];
        foreach ($this->currentModule->fields as $field) {
            if ($field->type === 'text') {
                continue;
            }
            $searchdefs['layout']['advanced_search'][] = $field->name;
        }
        $defModel = new OutputModel();
        $defModel->addArrayValue('searchdefs', $this->currentModuleName, $searchdefs);
        return $defModel->writeToString();
    }

    /**
     * @return string
     */
    protected function process_SearchFields()
    {
        $searchFields = [];
        require $this->currentFilePath;
        $searchFields = $searchFields['BasicModule'];
        foreach ($this->currentModule->fields as $field) {
            if ($field->type === 'text') {
                continue;
            }
            $searchFields[$field->name] = ['query_type' => 'default'];
        }
        $defModel = new OutputModel();
        $defModel->addArrayValue('searchFields', $this->currentModuleName, $searchFields);
        return $defModel->writeToString();
    }

    /**
     * @return string
     */
    protected function process_dashletviewdefs()
    {
        $searchFields = [
            'date_entered' => ['default' => '',],
            'date_modified' => ['default' => '',],
        ];
        $columns = [
            'date_entered' => ['width' => '15', 'label' => 'LBL_DATE_ENTERED', 'default' => true,],
            'date_modified' => ['width' => '15', 'label' => 'LBL_DATE_MODIFIED', 'default' => true,],
        ];
        foreach ($this->currentModule->fields as $key => $field) {
            $searchFields[$field->name] = ['default' => '',];
            $columns[$field->name] = ['width' => '15', 'label' => $field->labelKey, 'default' => false,];
        }
        $viewDefModel = new OutputModel();
        $viewDefModel->addArrayValue('dashletData', [$this->currentModuleName . 'Dashlet', 'searchFields'], $searchFields);
        $viewDefModel->addArrayValue('dashletData', [$this->currentModuleName . 'Dashlet', 'columns'], $columns);
        return $viewDefModel->writeToString();
    }

    /**
     * @return string
     */
    protected function process_listviewdefs()
    {
        $listViewDefs = [];
        $defaultFields = 0;
        foreach ($this->currentModule->fields as $key => $field) {
            $addThisField = true;
            if ($defaultFields >= 2 || $field->type === 'text' || $field->name === 'sa_id') {
                $addThisField = false;
            }
            if ($addThisField) {
                $defaultFields++;
            }
            $listViewDefs[strtoupper($field->name)] = [
                'width' => '20',
                'label' => $field->labelKey,
                'default' => $addThisField,
                'link' => false,
            ];
        }
        $listViewDefs['DATE_ENTERED'] = [
            'width' => '20',
            'label' => 'LBL_DATE_ENTERED',
            'default' => true,
            'link' => false,
        ];
        $listViewDefs['DATE_MODIFIED'] = [
            'width' => '20',
            'label' => 'LBL_DATE_MODIFIED',
            'default' => true,
            'link' => false,
        ];
        $viewDefModel = new OutputModel();
        $viewDefModel->addArrayValue('listViewDefs', $this->currentModuleName, $listViewDefs);
        return $viewDefModel->writeToString();
    }

    /**
     * @return string
     */
    protected function process_popupdefs()
    {
        $moduleName = $this->currentModuleName;
        $lModuleName = strtolower($moduleName);
        $popupMeta = array(
            'moduleMain' => $moduleName,
            'varName' => $moduleName,
            'orderBy' => $lModuleName . '.id',
            'whereClauses' => array(
                'name' => $lModuleName . '.id',
            ),
            'searchInputs' => array(
            ),
        );
        foreach ($this->currentModule->fields as $field) {
            if ($field->type === 'text') {
                continue;
            }
            $popupMeta['whereClauses'][$field->name] = $lModuleName . '.' . $field->name;
            $popupMeta['searchInputs'][] = $field->name;
            $popupMeta['searchdefs'][$field->name] = ['name' => $field->name, 'type' => $field->type, 'label' => $field->labelKey];
        }
        $viewDefModel = new OutputModel();
        $viewDefModel->addArrayValue('popupMeta', [], $popupMeta);
        return $viewDefModel->writeToString();
    }

    /**
     * This is the default subpanel
     *
     * @return string
     */
    protected function process_default()
    {
        $subpanel_layout = [];
        $defaultFields = 0;
        require $this->currentFilePath;
        $editButton = $subpanel_layout['list_fields']['edit_button'];
        $removeButton = $subpanel_layout['list_fields']['remove_button'];
        $subpanel_layout['list_fields'] = [];
        foreach ($this->currentModule->fields as $key => $field) {
            if ($defaultFields >= 2 || $field->type === 'text' || $field->name === 'sa_id') {
                continue;
            }
            $defaultFields++;
            $subpanel_layout['list_fields'][$field->name] = [
                'width' => '20%',
                'vname' => $field->labelKey,
            ];
        }
        $subpanel_layout['list_fields']['date_entered'] = [
            'width' => '20%',
            'vname' => 'LBL_DATE_ENTERED',
        ];
        $subpanel_layout['list_fields']['date_modified'] = [
            'width' => '20%',
            'vname' => 'LBL_DATE_MODIFIED',
        ];
        $subpanel_layout['list_fields']['editButton'] = $editButton;
        $subpanel_layout['list_fields']['remove_button'] = $removeButton;
        $viewDefModel = new OutputModel();
        $viewDefModel->addArrayValue('subpanel_layout', [], $subpanel_layout);
        return $this->handleNonMethodFiles($viewDefModel->writeToString());
    }

    /**
     * @return array
     */
    protected function buildFieldViewArray($viewType)
    {
        $currentRow = [];
        $viewRows = [];
        $doneFields = [];
        $bufferRows = [];

        $fields = $this->currentModule->fields;
        foreach ($fields as $key => $field) {
            if (in_array($field->name, $doneFields)) {
                continue;
            }
            if ($field->type === 'text') {
                $bufferRows[] = [$field->name];
                continue;
            }
            if (array_key_exists($key + 1, $fields) && $fields[$key + 1]->name === $field->name . '_unit') {
                $bufferRows[] = [
                    $field->name,
                    $fields[$key + 1]->name,
                ];
                $doneFields[] = $fields[$key + 1]->name;
                continue;
            }
            if (array_key_exists($key + 1, $fields) && $fields[$key + 1]->name === str_replace('_start', '_end', $field->name)) {
                $bufferRows[] = [
                    $field->name,
                    $fields[$key + 1]->name,
                ];
                $doneFields[] = $fields[$key + 1]->name;
                continue;
            }
            $currentRow[] = $field->name;
            if (count($currentRow) > 1) {
                $this->addRowToRowList($currentRow, $viewRows, $bufferRows);
            }
        }
        if (count($currentRow) === 1) {
            $this->addRowToRowList($currentRow, $viewRows, $bufferRows);
        }

        foreach ($this->currentModule->relationships as $relationship) {
            if (in_array($relationship->type, ['one-to-many', 'one-to-one']) && $this->currentModuleName === $relationship->suiteRight) {
                $currentRow[] = $relationship->suiteIdentifier . '_name';
            }
            if (in_array($relationship->type, ['many-to-one', 'one-to-one']) && $this->currentModuleName === $relationship->suiteLeft) {
                $currentRow[] = $relationship->suiteIdentifier . '_name';
            }
            if (count($currentRow) > 1) {
                $this->addRowToRowList($currentRow, $viewRows, $bufferRows);
            }
        }
        if (count($currentRow) === 1) {
            $this->addRowToRowList($currentRow, $viewRows, $bufferRows);
        }

        return $viewRows;
    }

    /**
     * @param $row
     * @param $rowList
     * @param $bufferRow
     */
    protected function addRowToRowList(&$currentRow, &$viewRows, &$bufferRows)
    {
        $newRow = [];
        foreach ($currentRow as $item) {
            $newRow[] = $item;
        }
        $viewRows[] = $newRow;
        foreach ($bufferRows as $row) {
            $viewRows[] = $row;
        }
        $bufferRows = [];
        $currentRow = [];
    }

    /**
     * @return array
     */
    protected function getDateModifiedRow()
    {
        return [
            [
                'name' => 'date_entered',
                'label' => 'LBL_DATE_ENTERED',
                'customCode' => '{$fields.date_entered.value} {$APP.LBL_BY} {$fields.created_by_name.value}',
            ],
            [
                'name' => 'date_modified',
                'label' => 'LBL_DATE_MODIFIED',
                'customCode' => '{$fields.date_modified.value} {$APP.LBL_BY} {$fields.modified_by_name.value}',
            ],
        ];
    }
}
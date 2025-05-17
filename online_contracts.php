<?php

// No direct access
defined('_JEXEC') or die('Restricted access');

// Require the abstract plugin class
require_once COM_FABRIK_FRONTEND . '/models/plugin-form.php';

include_once "htmltodoc/htmltodoc.class.php";

use Joomla\Filesystem\File;
use Joomla\CMS\Factory;

class PlgFabrik_FormOnline_contracts extends PlgFabrik_Form
{
    protected $modeloElement;
    protected $oldModelo;
    protected $newModelo;

    public function getTopContent() {
        $groupIdForm = $this->getParams()->get("groupid_form");
        $groups = array_keys($this->getModel()->getGroups());

        $opts = new StdClass;
		$opts->groupsId = $groups;
        $opts->groupIdForm = $groupIdForm;
    }

    protected function loadJS($opts) {
		$ext    = FabrikHelperHTML::isDebug() ? '.js' : '-min.js';

		$optsJson = json_encode($opts);
		$jsFiles = array();

		$jsFiles['Fabrik'] = 'media/com_fabrik/js/fabrik.js';
		$jsFiles['FabrikOnlineContracts'] = '/plugins/fabrik_form/online_contracts/online_contracts' . $ext;
		$script = "var onlineContracts = new FabrikOnlineContracts($optsJson);";
		FabrikHelperHTML::script($jsFiles, $script);
	}

    protected function getFieldsModelo($id, $tableName) {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query  = $db->getQuery(true);

        $query
            ->select($tableName[0] . '.ordem, campo, texto')
            ->from($tableName[0])
            ->join('INNER', $tableName[1] . ' ON ' . $tableName[0] . '.clausula = ' . $tableName[1] . '.parent_id')
            ->where($tableName[0] . '.parent_id = ' . (int) $id);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    protected function getTableGroup($group) {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query  = $db->getQuery(true);
        
        $query
            ->select('db_table_name')
            ->from('#__fabrik_formgroup g')
            ->join('INNER', '#__fabrik_lists l ON g.form_id = l.id')
            ->where('group_id = ' . (int) $group);

        $db->setQuery($query);
        
        return $db->loadResult();
    }

    protected function setModeloElement() {
        $formModel  = $this->getModel();
        $params     = $this->getParams();
        
        $elementId    = $params->get('element_dbjoin');
        $elementModel = $formModel->getElement($elementId, true)->element;

        $obj = new stdClass();
        $obj->name   = $elementModel->name;
        $obj->params = json_decode($elementModel->params);

        $this->modeloElement = $obj;
    }

    protected function setModelo($type) {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $formModel  = $this->getModel();
        $listName   = $formModel->getTableName();
        $rowId      = $formModel->formData[$listName . '___id'];

        if (!$rowId) {
            return;
        }

        $modeloElement = $this->modeloElement;

        $query = $db->getQuery(true);
        $query 
            ->select($modeloElement->name)
            ->from($listName)
            ->where('id = ' . (int) $rowId);

        $db->setQuery($query);

        $data = $db->loadResult();

        if ($data) {
            $query = $db->getQuery(true);
            $query
                ->select($modeloElement->params->join_val_column)
                ->from($modeloElement->params->join_db_name)
                ->where('id = ' . (int) $data);

            $db->setQuery($query);
            
            $data = $db->loadResult();
        }

        if ($type === 'old') {
            $this->oldModelo = $data;
        } else {
            $this->newModelo = $data;
        }
    }

    protected function formatFieldsModelo ($fields, $rowId) {
        $rows = array();

        foreach ($fields as $field) {
            $obj = new stdClass();
            
            $obj->id        = 0;
            $obj->parent_id = $rowId;
            $obj->campo     = $field->campo;
            $obj->texto     = $field->texto;
            $obj->ordem     = $field->ordem;

            $rows[] = $obj;
        }

        return $rows;
    }

    public function onBeforeProcess() {
        $this->setModeloElement();
        $this->setModelo('old');
    }

    protected function updateFields($rowId, $table, $rows) {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query  = $db->getQuery(true);
        
        $query
            ->delete($table)
            ->where("parent_id = " . (int) $rowId);

        $db->setQuery($query);
        $db->execute();

        foreach ($rows as $row) {
            $db->insertObject($table, $row, 'id');
        }
    }

    protected function setFields() {
        $formModel  = $this->getModel();
        $params     = $this->getParams();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $rowId      = $formModel->formData[$formModel->getTableName() . '___id'];
        
        $modeloElement   = $this->modeloElement;
        $groupIdForm     = $params->get('groupid_form');
        $groupIdBloco    = $params->get('groupid_clausula_bloco');
        $groupIdClausula = $params->get('groupid_modelo_clausula');

        $tableGroupBloco = $this->getTableGroup($groupIdBloco);

        $tableNameBloco  = $tableGroupBloco . '_' . $groupIdBloco . '_repeat';
        $tableNameForm   = $formModel->getTable()->db_table_name . '_' . $groupIdForm . '_repeat';
        $tableNameModelo = $modeloElement->params->join_db_name . '_' . $groupIdClausula . '_repeat';
        
        $rowIdModelo     = $input->getInt($formModel->getTable()->db_table_name . '___' . $modeloElement->name)[0];
        $fieldsModelo    = $this->getFieldsModelo($rowIdModelo, array($tableNameModelo, $tableNameBloco));

        $rows = $this->formatFieldsModelo($fieldsModelo, $rowId);

        $this->updateFields($rowId, $tableNameForm, $rows);
    }

    protected function getActualFields() {
        $params     = $this->getParams();
        $formModel  = $this->getModel();

        $groupIdForm    = $params->get('groupid_form');
        $rowId          = $formModel->formData[$formModel->getTableName() . '___id'];
        $tableNameForm  = $formModel->getTableName() . '_' . $groupIdForm . '_repeat';

        $field = new stdClass();

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query  = $db->getQuery(true);
        
        $query
            ->select('campo')
            ->from($tableNameForm)
            ->where('parent_id = ' . (int)$rowId);

        $db->setQuery($query);

        $field->campo = $db->loadColumn();

        $query = $db->getQuery(true);
        $query
            ->select('texto')
            ->from($tableNameForm)
            ->where('parent_id = ' . (int)$rowId);

        $db->setQuery($query);

        $field->texto = $db->loadColumn();

        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $field->texto[0]);

        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            $src = COM_FABRIK_LIVESITE . "/" . trim($src);
            $img->setAttribute('src', $src);
        }
        $field->texto[0] = $dom->saveHTML();

        return $field;
    }

    public function onAfterProcess()
    {
        $formModel = $this->getModel();
        $this->setModelo('new');
         
        if ($this->oldModelo !== $this->newModelo) {
            $this->setFields();
        }

        $obj = $this->getActualFields();
        
        $tableName  = $formModel->getTableName();
        $rowId      = $formModel->formData[$tableName . '___id'];

        $this->makeDocuments($obj, $rowId);
    }

    protected function getCabecalhoAndRodape() {
        $params = $this->getParams();

        $groupIdModelo = $params->get('groupid_modelo_clausula');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery("SELECT params FROM #__fabrik_groups WHERE id = " . (int) $groupIdModelo);
        $result = json_decode($db->loadResult());

        $data = new stdClass();

        $dom_cabecalho = new DOMDocument();
        !empty($result->intro) ? $dom_cabecalho->loadHTML('<?xml encoding="utf-8" ?>' . $result->intro) : '';
        $imgs = $dom_cabecalho->getElementsByTagName('img');

        for($i = 0; $i < $imgs->length; $i++) {
            $src     = $imgs->item($i)->getAttribute('src');
            $new_src = COM_FABRIK_LIVESITE . $src;

            $imgs->item($i)->setAttribute('src', $new_src);
        }

        $data->cabecalho = $dom_cabecalho->saveHTML();

        $dom_rodape = new DOMDocument();
        !empty($result->outro) ? $dom_rodape->loadHTML('<?xml encoding="utf-8" ?>' . $result->outro) : '';
        $imgs = $dom_rodape->getElementsByTagName('img');
        
        for($i = 0; $i < $imgs->length; $i++) {
            $src     = $imgs->item($i)->getAttribute('src');
            $new_src = COM_FABRIK_LIVESITE . $src;

            $imgs->item($i)->setAttribute('src', $new_src);
        }

        $data->rodape = $dom_rodape->saveHTML();

        return $data;
    }

    protected function getHTML($obj) {
        $data       = $this->getCabecalhoAndRodape();
        $rodape     = $data->rodape;
        $cabecalho  = $data->cabecalho;

        $i = 0;
        $body ='';

        foreach ($obj->texto as $item) {     
            // Dont remove <p> in the first block
            if ($i) {
            // Remove <p stuff ></> from begging 
                $text = explode('>', $item, 2);
                $item = $text[1];
            }
            // Remove </p> in the end
            $item = substr($item, 0, -4);       
            $body      .= $item . $obj->campo[$i];
            $i++;
        }

        $dom = new DOMDocument();
        
        $style  = 'p { text-align:justify; }';
        $style  = '<style>' . $style . '</style>';
        
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $style . $body);

        $output = $cabecalho . $dom->saveHTML() . $rodape;

        return $output;
    }

    protected function makeDocuments($obj, $rowId) {
        $output = $this->getHTML($obj);
        $params     = $this->getParams();
        
        if($params->get('diff_id') != '') {
            $idDiff = str_replace(' ', '_', $params->get('diff_id')) . '_';
        } else {
            $idDiff = '';
        }

        $path_local = JPATH_BASE .  '/images/online_contracts/' . $idDiff . 'contract' . $rowId . '.pdf';
        $path_html  = JPATH_BASE .  '/images/online_contracts/' . $idDiff . 'contract' . $rowId . '.html';
        $path_doc   = 'file://' . JPATH_BASE .  '/images/online_contracts/' . $idDiff . 'contract' . $rowId . '.doc';

        is_file($path_local) ? File::delete($path_local) : null;
        is_file($path_html) ? File::delete($path_html) : null;
        is_file($path_doc) ? File::delete($path_doc) : null;
        File::write($path_html, $output);

        shell_exec('xvfb-run wkhtmltopdf ' . $path_html . ' ' . $path_local);

        $htd = new HTML_TO_DOC();
        $htd->createDoc($path_html, $path_doc);
    }

    // Show buttons for pdf, doc, html 
    
    public function getBottomContent_result($c) {
        $params     = $this->getParams();

        if($params->get('diff_id') != '') {
            $idDiff = str_replace(' ', '_', $params->get('diff_id')) . '_';
        } else {
            $idDiff = '';
        }

        $input  = $this->app->input;
        $rid  = $input->get('rowid', '', 'string');
        
        if ($rid) {
        
        $buttons = '<div id="buttons_online_contract" style="width:100%;text-align: center;"> <div class="btn-group" style="margin: 10px;">
        
        <a class="btn btn-primary btn-pitt" href="' . COM_FABRIK_LIVESITE . 'images/online_contracts/' . $idDiff . 'contract' . $rid . '.pdf?'. rand(99,9999) .'"  target="_blank" style="margin: 5px;">Ver PDF</a>
        <a class="btn btn-primary btn-pitt" href="' . COM_FABRIK_LIVESITE . 'images/online_contracts/' . $idDiff . 'contract' . $rid . '.doc?'. rand(99,9999) .'"  target="_blank" style="margin: 5px;">Ver DOC</a>       
        <a class="btn btn-primary btn-pitt" href="' . COM_FABRIK_LIVESITE . 'images/online_contracts/' . $idDiff . 'contract' . $rid . '.html?'. rand(99,9999) .'" target="_blank" style="margin: 5px;">Ver HTML</a></div></div>';
        
        return $buttons;
        
        }
    }


    public function onDeleteRowsForm(&$groups)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $input = $this->app->getInput();
        $this->setId($input->get('Itemid'));
        $params     = $this->getParams();
        
        if($params->get('diff_id') != '') {
            $idDiff = str_replace(' ', '_', $params->get('diff_id')) . '_';
        } else {
            $idDiff = '';
        }
        
        $formModel = $this->getModel();
        $ids       = $input->get('ids');
        
        $groupid_form = $this->getParams()->get('groupid_form');
        $table        = $formModel->getTable()->db_table_name . '_' . $groupid_form . '_repeat';
        
        $db->setQuery("SELECT COUNT(`table_name`) FROM `INFORMATION_SCHEMA`.`tables` WHERE `table_schema` = (SELECT DATABASE()) AND `table_name` = '$table';")->execute();

        $table_exist = (bool) $db->setQuery("SELECT COUNT(`table_name`) FROM `INFORMATION_SCHEMA`.`tables` WHERE `table_schema` = (SELECT DATABASE()) AND `table_name` = '$table';")->loadResult();
        if($table_exist) {
            foreach ($ids as $id) {

                $db->setQuery("DELETE FROM " . $table . " WHERE parent_id = " . (int)$id);
                $db->execute();

                File::delete(JPATH_BASE . '/images/online_contracts/' . $idDiff . 'contract' . $id . '.pdf');
            }
        }

        return true;
    }
}
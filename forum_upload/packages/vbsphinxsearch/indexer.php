<?php
if (!defined('VB_ENTRY'))
    die('Access denied.');
require_once(DIR . '/vb/search/itemindexer.php');


class vBSphinxSearch_Indexer extends vB_Search_ItemIndexer
{

    protected $_id_list;
    protected $_content_type_id;

    /**
     * Enter description here...
     *
     * @param array $fields
     *   this is an array of field-value pairs. We need to have at least
     *   primaryid and contenttype. The id may come as primaryid or id.
     */
    public function index($fields)
    {
        if (array_key_exists('id', $fields) AND !(array_key_exists('primaryid', $fields)))
        {
            $fields['primaryid'] = $fields['id'];
            unset($fields['id']);
        }

        if (!array_key_exists('primaryid', $fields) OR !array_key_exists('contenttypeid', $fields))
        {
            return false;
        }

        $this->_set_content_type_id($fields['contenttypeid']);
        $this->_id_list = array($fields['primaryid']);
        $this->_mark_as_deleted();
        $this->_write_to_queue($fields['primaryid']);
    }

    /**
     * Delete the record from the index
     *
     * If its the last record in its group, then nuke the group record too.
     *
     * @param int $contenttype
     * @param int $id
     */
    public function delete($content_type_id, $id)
    {
        $this->_set_content_type_id($content_type_id);
        $this->_id_list = array($id);
        $this->_mark_as_deleted();
    }

    public function merge_group($content_type_id, $old_group_id, $new_group_id)
    {
        throw new Exception('Index controlle must be used');
    }

    public function group_data_change($fields)
    {
        $this->_set_content_type_id($fields['contenttypeid']);
        if ($this->_fetch_object_id_list($fields['groupid'], true))
        {
            return $this->_update_attributes($fields);
        }
    }

    public function delete_group($content_type_id, $group_id)
    {
        $this->_set_content_type_id($content_type_id);
        if ($this->_fetch_object_id_list($group_id))
        {
            $this->_mark_as_deleted();
        }
    }

    protected function _update_attributes($fields)
    {
        if (is_null($this->_content_type_id) OR empty($this->_id_list) OR !is_array($this->_id_list))
        {
            return false;
        }
        $indexes = vBSphinxSearch_Core::get_sphinx_index_map($this->_content_type_id);
        if (empty($indexes))
        {
            return false;
        }
        $keys = array_keys($fields);
        foreach ($this->_id_list as $id)
        {
            $document_id = $this->_generate_sphinx_document_id($id);
            $values[$document_id] = array_map('intval', array_values($fields));
        }

        $count_updated = 0;
        foreach ($indexes as $index)
        {
            $update_result = $this->_update_attributes_in_single_index($index, $keys, $values);
            if (false === $update_result)
            {
                $message = "\n\nSphinx: Can't update attributes\nUse index: $index\n" . var_export($values, true) . "\n";
                vBSphinxSearch_Core::log_errors($message);
            }
            else
            {
                $count_updated += $update_result;
            }
        }
        return $count_updated;
    }

    protected function _update_attributes_in_single_index($index, $keys, $values)
    {
        $cl = vBSphinxSearch_Core::get_sphinx_client();
        for ($i = 0; $i < vBSphinxSearch_Core::RECONNECT_LIMIT; $i++)
        {
            $count_updated = $cl->UpdateAttributes($index, $keys, $values);
            if (-1 != $count_updated)
            {
                return $count_updated;
            }
        }
        return false;
    }

    protected function _mark_as_deleted()
    {

        $fields = array('deleted' => 1);
        return $this->_update_attributes($fields);
    }

    protected function _generate_sphinx_document_id($id)
    {
        return ($id * vBSphinxSearch_Core::SPH_DOC_ID_PACK_MULT + $this->_content_type_id);
    }

    protected function _write_to_queue($id)
    {
        if (is_null($this->_content_type_id) OR 0 == (int)$id)
        {
            return false;
        }
        global $vbulletin;
        $db = $vbulletin->db;

        $sql = 'INSERT INTO ' . TABLE_PREFIX . 'vbsphinxsearch_queue  (`contenttypeid`, `primaryid`)
			VALUES (' . $this->_content_type_id . ', ' . $id . ')
			ON DUPLICATE KEY UPDATE 
                `contenttypeid` = VALUES(`contenttypeid`), `primaryid` = VALUES(`primaryid`), `done` = 0';
        return $db->query_write($sql);
    }

    protected function _fetch_object_id_list($group_id, $only_first = false)
    {
        if (is_null($this->_content_type_id) OR 0 == (int)$group_id)
        {
            return false;
        }
        $this->_id_list = array();
        $cl = vBSphinxSearch_Core::get_sphinx_client();
        
        $limit = vBSphinxSearch_Core::DEFAULT_LIMIT;
        $cl->SetLimits(0, $limit, $limit);

        $cl->ResetFilters();
        $cl->SetFilter('groupid', array($group_id));
        if (true == $only_first)
        {
            $cl->SetFilter('isfirst', array(1));
        }

        $indexes = implode(",", vBSphinxSearch_Core::get_sphinx_index_map($this->_content_type_id));
        if (empty($indexes))
        {
            return false;
        }
        for ($i = 0; $i < vBSphinxSearch_Core::RECONNECT_LIMIT; $i++)
        {
            $res = $cl->Query('', $indexes);
            $error = $cl->GetLastError();
            if (!$error)
            {
                break;
            }
        }
        if ($error)
        {
            $message = "\n\nSphinx: Can't get primaryid list for groupid=$group_id\nUsed indexes: $indexes\n Error:\n$error\n";
            vBSphinxSearch_Core::log_errors($message);
        }
        if (!is_null($res) AND is_array($res["matches"]))
        {
            foreach ($res["matches"] as $docinfo)
            {
                $this->_id_list[] = $docinfo['attrs']['primaryid'];
            }
            return true;
        }
        return false;
    }

    protected function _set_content_type_id($content_type_id)
    {
        $indexes = vBSphinxSearch_Core::get_sphinx_index_map($content_type_id);
        if (empty($indexes))
        {
            $this->_content_type_id = NULL;
            return false;
        }
        $this->_content_type_id = $content_type_id;
        return true;
    }

}

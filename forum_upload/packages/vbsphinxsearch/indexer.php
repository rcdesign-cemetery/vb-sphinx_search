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
        $this->_write_to_queue(array($fields['primaryid']));
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
        throw new Exception('Index controller must be used');
    }

    public function group_data_change($fields)
    {
        $this->_set_content_type_id($fields['contenttypeid']);
        if ($this->_fetch_object_id_list($fields['groupid'], true))
        {
            return $this->_write_to_queue($this->_id_list);
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

    protected function _mark_as_deleted()
    {
        $this->_write_to_queue($this->_id_list);
        return true;
    }

    protected function _write_to_queue($ids)
    {
        if (is_null($this->_content_type_id) OR
            !is_array($ids) OR empty($ids))
        {
            return false;
        }
        $db = vB::$vbulletin->db;
        $values = array();
        foreach ($ids as $id)
        {
            $values[] = '(' . $this->_content_type_id . ', ' . $id . ')';
        }

        $sql = 'INSERT INTO ' . TABLE_PREFIX . 'vbsphinxsearch_queue  (`contenttypeid`, `primaryid`)
                VALUES ' . implode(', ', $values) . '
                ON DUPLICATE KEY UPDATE
                    `contenttypeid` = VALUES(`contenttypeid`), `primaryid` = VALUES(`primaryid`), `done` = 0';
        return $db->query_write($sql);
    }

    protected function _fetch_object_id_list($group_id, $only_first = false)
    {
        if (is_null($this->_content_type_id) OR 0 == (int) $group_id)
        {
            return false;
        }
        $this->_id_list = array();

        $limit = vBSphinxSearch_Core::DEFAULT_LIMIT;
        $indexes = implode(",", vBSphinxSearch_Core::get_sphinx_index_map($this->_content_type_id));

        $query = 'SELECT *
                    FROM 
                        ' . $indexes . '
                    WHERE
                        contenttypeid = ' . $this->_content_type_id . ' AND
                        groupid = ' . $group_id . ($only_first ? ' AND isfirst = 1' : '') . '
                    LIMIT ' . $limit . ' OPTION max_matches=' . $limit;

        $this->_id_list = array();
        for ($i = 0; $i < vBSphinxSearch_Core::RECONNECT_LIMIT; $i++)
        {
            $con = vBSphinxSearch_Core::get_sphinxql_conection();
            if (false != $con)
            {
                $result_res = mysql_query($query, $con);
                if ($result_res)
                {
                    while ($docinfo = mysql_fetch_assoc($result_res))
                    {
                        $this->_id_list[] = $docinfo['primaryid'];
                    }
                    return true;
                }
            }
        }
        $error = mysql_error();
        $message = "\n\nSphinx: Can't get primaryid list for groupid=$group_id\nUsed indexes: $indexes\n Error:\n$error\n";
        vBSphinxSearch_Core::log_errors($message);

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

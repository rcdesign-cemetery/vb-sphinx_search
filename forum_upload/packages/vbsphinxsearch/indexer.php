<?php
if (!defined('VB_ENTRY'))
    die('Access denied.');
require_once(DIR . '/vb/search/itemindexer.php');

/**
 * Indexer for Sphinx Search engine.
 *
 * Main index dosen't need special actions, but for Delta index need queue.
 * This class write document id to queue table.
 *
 */
class vBSphinxSearch_Indexer extends vB_Search_ItemIndexer
{

    protected $_id_list;

    protected $_content_type_id;

    /**
     * Write id to queue table
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
     * Mark record as deleted
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

    /**
     * Added for compatibility with original search engine
     */
    public function merge_group($content_type_id, $old_group_id, $new_group_id)
    {
        throw new Exception('Index controller must be used');
    }

    /**
     * Write group to (re)index
     *
     * Attention this function load up sphinxd.
     * It beter to fetch group in index controller and reindex one by one
     */
    public function group_data_change($fields)
    {
        $this->_set_content_type_id($fields['contenttypeid']);
        if ($this->_fetch_object_id_list($fields['groupid'], true))
        {
            return $this->_write_to_queue($this->_id_list);
        }
    }

    /**
     * Mark group as deleted
     *
     * Attention this function load up sphinxd.
     * It beter to fetch group in index controller and reindex one by one
     */
    public function delete_group($content_type_id, $group_id)
    {
        $this->_set_content_type_id($content_type_id);
        if ($this->_fetch_object_id_list($group_id))
        {
            $this->_mark_as_deleted();
        }
    }

    /**
     * Mark all items from $this->_id_list as deleted
     * This function was added for dirrect updete index attrebutes.
     * But this feature dosen't support at this time
     */
    protected function _mark_as_deleted()
    {
        $this->_write_to_queue($this->_id_list);
        return true;
    }

    /**
     * write id to queue table
     */
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

    /**
     * Fetch list of id by group_id.
     * This is support function used for mass operations.
     * Attention this function load up sphinxd.
     */
    protected function _fetch_object_id_list($group_id, $only_first = false)
    {
        if (is_null($this->_content_type_id) OR 0 == (int) $group_id)
        {
            return false;
        }
        $this->_id_list = array();

        $limit = vBSphinxSearch_Core::SPH_DEFAULT_RESULTS_LIMIT;
        $indexes = implode(",", vBSphinxSearch_Core::get_sphinx_index_map($this->_content_type_id));

        $query = 'SELECT *
                    FROM 
                        ' . $indexes . '
                    WHERE
                        contenttypeid = ' . $this->_content_type_id . ' AND
                        groupid = ' . $group_id . ($only_first ? ' AND isfirst = 1' : '') . '
                    LIMIT ' . $limit . ' OPTION max_matches=' . $limit;

        $this->_id_list = array();
        for ($i = 0; $i < vBSphinxSearch_Core::SPH_RECONNECT_LIMIT; $i++)
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

    /**
     * Setter for content_type_id, also check whether this type to be indexed
     */
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

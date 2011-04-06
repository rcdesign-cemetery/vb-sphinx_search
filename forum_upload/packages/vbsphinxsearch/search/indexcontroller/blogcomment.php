<?php

class vBSphinxSearch_Search_IndexController_BlogComment extends vBBlog_Search_IndexController_BlogComment
{
    /*
     * Called on entry hard-delete, to kill all comments in search index
     * 
     * (!) This operation is a bit long, but we don't remove blog
     * entries very often
     */
    public function delete_group($group_id)
    {
        global $vbulletin;
        $indexer = vBSphinxSearch_Core::get_instance()->get_core_indexer();

        $sql = "SELECT
                    " . $this->get_contenttypeid() . " AS contenttypeid,
                    blog_text.blogtextid AS primaryid
                FROM
                    " . TABLE_PREFIX . "blog_text AS blog_text
                LEFT JOIN
                    " . TABLE_PREFIX . "blog AS blog ON blog.firstblogtextid = blog_text.blogtextid
            	WHERE
                    blog_text.blogid = " . intval($group_id) . "
                    AND blog.blogid IS NULL";
        $res = $vbulletin->db->query_read_slave($sql);
        while ($row = $vbulletin->db->fetch_array($res))
        {
            $indexer->delete($row['contenttypeid'], $row['primaryid']);
        }
        return true;
    }

    /*
     * Called on each
     *  - new comment
     *  - entry add/update
     *  - blog entry soft delete
     * 
     * 1. Note, that update of first element will be done anyway
     * That's enougth for most cases, so skip update of
     * comments unfo. It will be fixed on daily reindex.
     * 
     * 2. If blog entry soft deleted, then place all comments to queue,
     * to mark as deleted
     */
    public function group_data_change($group_id)
    {
        global $vbulletin;
        
        $sql = "SELECT
                    `state`
                FROM
                    " . TABLE_PREFIX . "blog
                WHERE
                    `blogid` = " . (int)$group_id;

        $entry_state = $vbulletin->db->query_first($sql);

        if (!$entry_state)
        {
            // somerthing gone wrong, entry not found at all
            return false;
        }
        
        if ('deleted' == $entry_state['state'])
        {
            // If entry soft deleted, then mark all comments as deleted
            $this->delete_group($group_id);
        }
        return true;
    }

}

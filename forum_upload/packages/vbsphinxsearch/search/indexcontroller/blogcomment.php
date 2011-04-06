<?php

class vBSphinxSearch_Search_IndexController_BlogComment extends vBBlog_Search_IndexController_BlogComment
{
    /*
     * Called on entry delete, to kill comments
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
     * Called on each new comment & entry add/update
     * 
     * Note, that update of first element will be done anyway
     * That's enougth for most cases, so skip update of
     * comments unfo. It will be fixed on daily reindex.
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
        $blog_state = $vbulletin->db->query_first($sql);
        if (!$blog_state)
        {
            //non existant blog.
            return false;
        }
        if ('deleted' == $blog_state['state'])
        {
            // soft blog delete
            $this->delete_group($group_id);
        }
        return true;
    }

}

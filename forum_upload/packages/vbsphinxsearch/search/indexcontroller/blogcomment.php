<?php

class vBSphinxSearch_Search_IndexController_BlogComment extends vBBlog_Search_IndexController_BlogComment
{
    public function delete_group($groupid)
    {
        $indexer = vB_Search_Core::get_instance()->get_core_indexer();
        $indexer->delete_group($this->get_contenttypeid(), $groupid);
    }

    public function group_data_change($groupid)
    {
        global $vbulletin;
        $sql = "SELECT
                bt.blogid AS groupid,
                " . $this->get_contenttypeid() . " AS contenttypeid,
                bt.blogtextid AS primaryid,
                bt.dateline AS dateline,
                b.lastcomment AS groupdateline,
    			bt.bloguserid AS userid,
            	IF(bt.state = 'visible', 1, 0) AS visible
            FROM " . TABLE_PREFIX . "blog_text AS bt
            JOIN " . TABLE_PREFIX . "blog AS b ON
                (b.blogid = bt.blogid AND b.firstblogtextid != bt.blogtextid)
            WHERE 
                b.firstblogtextid <> bt.blogtextid AND 
                b.blogid = " . intval($groupid);
        $data = $vbulletin->db->query_first_slave($sql);
        if (!$data)
        {
            return;
        }
        $indexer = vB_Search_Core::get_instance()->get_core_indexer();
        $indexer->group_data_change($data);
    }

}

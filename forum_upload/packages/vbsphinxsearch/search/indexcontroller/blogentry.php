<?php if (!defined('VB_ENTRY')) die('Access denied.');

/**
 * Index Controller for blog entrys
 *
 */
class vBSphinxSearch_Search_IndexController_BlogEntry extends vBBlog_Search_IndexController_BlogEntry
{
	
	/**
	 * Index "group message" (blog entry).
     * 
     * Due to stupid schema design, indexed data placed in `blog_text`
     * table. But we receive ID from `blog` table. Should remap it,
     * prior to place to queue 
	 *
	 * @param int $id
	 */
	public function index($id)
    {
        $blog_text_id = $this->_get_blog_text_id($id);
        if (!$blog_text_id)
        {
            return false;
        }
        $blog_entry_info = array(
            'primaryid' => $blog_text_id,
            'contenttypeid' => $this->get_contenttypeid(), 
        );
        $indexer = vB_Search_Core::get_instance()->get_core_indexer();
        return $indexer->index($blog_entry_info );
    }

	/**
	 * Delete "group message" (blog entry).
     * 
     * Due to stupid schema design, indexed data placed in `blog_text`
     * table. But we receive ID from `blog` table. Should remap it,
     * prior to place to queue 
	 *
	 * @param int $id
	 */
    public function delete($id)
    {
        $blog_text_id = $this->_get_blog_text_id($id);
        if (!$blog_text_id)
        {
            return false;
        }
        $indexer = vB_Search_Core::get_instance()->get_core_indexer();
        return $indexer->delete($this->get_contenttypeid(), $blog_text_id);

    }

    protected function _get_blog_text_id($blog_id)
    {
        global $vbulletin;
        $sql = "SELECT
                    blog.firstblogtextid AS primaryid
                FROM
                    " . TABLE_PREFIX . "blog as blog
            	WHERE
                    blog.blogid = " . intval($blog_id);
        $row = $vbulletin->db->query_first_slave($sql);
        if ($row)
        {
            return $row['primaryid'];
        }
        return 0;
    }
}


<?php if (!defined('VB_ENTRY')) die('Access denied.');

/**
 * Index Controller for blog entrys
 *
 */
class vBSphinxSearch_Search_IndexController_BlogEntry extends vBBlog_Search_IndexController_BlogEntry
{
	
	/**
	 * Index group message
	 *
	 * @param int $id
	 */
	public function index($id)
	{
		global $vbulletin;
        $sql = "SELECT
                    " . $this->get_contenttypeid() . " AS contenttypeid,
                    blog.firstblogtextid AS primaryid
                FROM
                    " . TABLE_PREFIX . "blog as blog
            	WHERE
                    blog.blogid = " . intval($id);

		$row = $vbulletin->db->query_first_slave($sql);
		if ($row)
		{
			$indexer = vB_Search_Core::get_instance()->get_core_indexer();
			$indexer->index($row);
		}
	}
}


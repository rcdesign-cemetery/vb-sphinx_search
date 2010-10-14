<?php if (!defined('VB_ENTRY')) die('Access denied.');

/**
 * Index Controller for blog entrys
 *
 * @package vBulletin
 * @subpackage Search
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
                    blog_text.blogtextid AS primaryid
                FROM
                    " . TABLE_PREFIX . "blog as blog
                LEFT JOIN
                    " . TABLE_PREFIX . "blog_text as blog_text ON blog.firstblogtextid = blog_text.blogtextid
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

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:44, Wed Sep 15th 2010
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
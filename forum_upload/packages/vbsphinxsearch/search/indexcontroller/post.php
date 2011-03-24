<?php if (!defined('VB_ENTRY')) die('Access denied.');

require_once (DIR . '/packages/vbforum/search/indexcontroller/post.php');
require_once (DIR . '/packages/vbdbsearch/indexer.php');


/**
 * Index controller for posts
 *
 */
class vBSphinxSearch_Search_IndexController_Post extends vBForum_Search_IndexController_Post
{
	/**
	 * Delete a range of posts
	 *
	 * @param int $start
	 * @param int $end
	 */
	public function delete_id_range($start, $end)
	{
		return false;
	}

	/**
	 * Index a thread
	 *
     * Add first post to queue for reindex 
     * TODO: review solution after migration to rt index structure
	 *
	 * @param int $id the thread id
	 */
	public function thread_data_change($id)
	{
		return $this->group_data_change($id);
	}

	public function group_data_change($id)
    {
        $head_post =  $this->_get_thread_primaryid($id);
        if (!$head_post)
        {
            return false;
        }
        $indexer = vBSphinxSearch_Core::get_instance()->get_core_indexer();
        return $indexer->index($head_post);
	}

	/**
     * Add first post to queue for reindex 
     * TODO: review solution after migration to rt index structure
	 *
	 * @param int $id the thread id
	 */
	public function delete_thread($id)
	{
        $head_post =  $this->_get_thread_primaryid($id);
        if (!$head_post)
        {
            return false;
        }
        $indexer = vBSphinxSearch_Core::get_instance()->get_core_indexer();
        return $indexer->delete($head_post['contenttypeid'], $head_post['primaryid']);
    }

    /**
     * Get first postid
     * 
     * @param int $id
     * @return mixed
     */
    protected function _get_thread_primaryid($id)
    {
        global $vbulletin;
        $sql = "SELECT
                    " . $this->contenttypeid . " AS contenttypeid,
                    `firstpostid` AS primaryid
                FROM
                    " . TABLE_PREFIX . "thread
                WHERE
                  `threadid` = $id";

        $head_post = $vbulletin->db->query_first($sql);
		if (!$head_post)
		{
			//non existant thread.
			return false;
        }
        return $head_post;
    }

	/**
     * We just pass this to the core indexer, which knows how to do this.
     */
	public function merge_group($oldid, $newid)
	{
        $this->delete_thread($oldid);
        $this->group_data_change($newid);
	}
}

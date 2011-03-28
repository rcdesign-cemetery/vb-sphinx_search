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
     * Added for compatibility with original search engine
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
	 * @param int $id the thread id
	 */
	public function thread_data_change($id)
	{
		return $this->group_data_change($id);
	}

    /**
     * Add first post to queue for reindex 
     * TODO: review solution after migration to rt index structure
	 *
     */
	public function group_data_change($id)
    {
        $first_post_info =  $this->_get_thread_primaryid($id);
        if (!$first_post_info)
        {
            return false;
        }
        $indexer = vBSphinxSearch_Core::get_instance()->get_core_indexer();
        return $indexer->index($first_post_info);
	}

	/**
     * Add first post to queue for reindex 
     * TODO: review solution after migration to rt index structure
	 *
	 * @param int $id the thread id
	 */
	public function delete_thread($id)
	{
        $first_post_info =  $this->_get_thread_primaryid($id);
        if (!$first_post_info)
        {
            return false;
        }
        $indexer = vBSphinxSearch_Core::get_instance()->get_core_indexer();
        return $indexer->delete($first_post_info['contenttypeid'], $first_post_info['primaryid']);
    }

    /**
     * Get first post info (array with contenttypeid and postid as keys)
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

        $first_post_info = $vbulletin->db->query_first($sql);
		if (!$first_post_info)
		{
			//non existant thread.
			return false;
        }
        return $first_post_info;
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

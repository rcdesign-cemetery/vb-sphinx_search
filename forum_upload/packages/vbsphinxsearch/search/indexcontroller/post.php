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
	 * By default this will look up all of the posts in a thread and calls the core
	 * indexer for each one
	 *
	 * @param int $id the thread id
	 */
	public function thread_data_change($id)
	{
		return $this->group_data_change($id);
	}

	public function group_data_change($id)
	{
		$thread = vB_Legacy_Thread::create_from_id($id);

		if (!$thread)
		{
			//skip non existant threads.
			return false;
		}

        // todo уточнить все поля
		$fields['contenttypeid'] = $this->contenttypeid;
		$fields['groupid'] = $thread->get_field('threadid');

        $fields['groupdateline'] = $thread->get_field('lastpost');
        $fields['groupuserid'] = $thread->get_field('postuserid');
        
        $fields['prefixcrc'] = sprintf("%u", crc32($thread->get_field('prefixid')));

        $fields['replycount'] = $thread->get_field('replycount');
        $fields['views'] = $thread->get_field('views');

        $fields['groupstart'] = $thread->get_field('dateline');
        $fields['visible'] = $thread->get_field('visible');
        $fields['groupopen'] = $thread->get_field('open');
        $fields['groupparentid'] = $thread->get_field('forumid');

		$indexer = vBSphinxSearch_Core::get_instance()->get_core_indexer();
		return $indexer->group_data_change($fields);
	}

	/**
	 * Delete all of the posts in a thread.
	 *
	 * By default this looks up all of the post ids in a thread and
	 * calls delete for each one
	 *
	 * @param int $id the thread id
	 */
	public function delete_thread($id)
	{
		$indexer = vBSphinxSearch_Core::get_instance()->get_core_indexer();
		$indexer->delete_group($this->contenttypeid, $id);
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

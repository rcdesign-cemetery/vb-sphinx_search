<?php if (!defined('VB_ENTRY')) die('Access denied.');

require_once (DIR . '/packages/vbforum/search/indexcontroller/socialgroupmessage.php');

class vBSphinxSearch_Search_IndexController_SocialGroupMessage extends vBForum_Search_IndexController_SocialGroupMessage
{

    /**
     * This function is never called
     * Added for compatibility with original search engine
     */
	public function merge_groups($oldid, $newid)
	{
        return true;
	}

    /**
     * This function is never called
     * Added for compatibility with original search engine
     */
	public function delete_group($id)
	{
        return true;
	}

    /**
     * This function is never called
     * Added for compatibility with original search engine
     */
	public function index_category($id)
	{
        return true;
	}

    /**
     * This function is never called
     * Added for compatibility with original search engine
     */
	public function merge_categories($oldid, $newid)
	{
        return true;
	}

    /**
     * This function is never called
     * Added for compatibility with original search engine
     */
	public function delete_category($id)
	{
        return true;
	}

}

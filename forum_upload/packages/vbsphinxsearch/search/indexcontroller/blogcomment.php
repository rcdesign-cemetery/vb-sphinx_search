<?php

class vBSphinxSearch_Search_IndexController_BlogComment extends vBBlog_Search_IndexController_BlogComment
{
    /*
     * Called on entry delete, to kill comments
     */
    public function delete_group($groupid)
    {
        // We alredy mark blog head for reindex
        // TODO: review solution after migration to rt index structure
        return true;
    }

    /*
     * Called on each new comment & entry add/update
     * 
     * Note, that update of first element will be done anyway
     * That's enougth for most cases, so skip update of
     * comments unfo. It will be fixed on daily reindex.
     */
    public function group_data_change($groupid)
    {
        return true;
    }

}

<?php

class vBSphinxSearch_Search_IndexController_BlogComment extends vBBlog_Search_IndexController_BlogComment
{
    public function delete_group($groupid)
    {
        // We alredy mark blog head for reindex
        // TODO: review solution after migration to rt index structure
        return true;
    }

    public function group_data_change($groupid)
    {
        // We alredy mark blog head for reindex
        // TODO: review solution after migration to rt index structure
        return true;
    }

}

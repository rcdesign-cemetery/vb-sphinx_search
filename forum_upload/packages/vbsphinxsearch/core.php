<?php
/**
 * Init all search classes fro indexer & searcher
 * + some basic low-level functions 
 */


if (!defined('VB_ENTRY'))
    die('Access denied.');

/**
 * Sphinx search engine, based on SphinxQL protocol.
 *
 * Note: vBulletin use stupid application design, that is why all methods are static
 */
class vBSphinxSearch_Core extends vB_Search_Core
{
    /**
     * Map for sphinx indexes.
     * For each content type used main+delta shema
     */
    protected static $_sphinx_index_map = array();

    /**
     * resouce for sphinx conection.
     * Note: for connection to sphinx used:
     *       http://php.net/manual/en/book.mysql.php
     */
    protected static $_sphinx_conection = NULL;

    /**
     * This const used then vBulletin resulys limit dosen't set
     */
    const SPH_DEFAULT_RESULTS_LIMIT = 50000;

    /**
     * Reconnections limit for query
     */
    const SPH_RECONNECT_LIMIT = 3;

    /**
     * Multiplier using to create sphinx docid based on the contenttypeid and item id
     */
    const SPH_DOC_ID_PACK_MULT = 64;

    /**
     * Mysql errorno used in reconnection
     */
    const SPH_CONNECTION_ERROR_NO = 2002;

    /**
     * Mandatory to overwrite for init specific search components
     *
     */
    static function init()
    {
        //register implementation objects with the search system.
        $search = vB_Search_Core::get_instance();
        $search->register_core_indexer(new vBSphinxSearch_Indexer());
        $search->register_index_controller('vBForum', 'Post', new vBSphinxSearch_Search_IndexController_Post());
        $search->register_index_controller('vBBlog', 'BlogComment', new vBSphinxSearch_Search_IndexController_BlogComment());
        $search->register_index_controller('vBBlog', 'BlogEntry', new vBSphinxSearch_Search_IndexController_BlogEntry());
        $search->register_index_controller('vBForum', 'SocialGroupMessage', new vBSphinxSearch_Search_IndexController_SocialGroupMessage());
        $__vBSphinxSearch_CoreSearchController = new vBSphinxSearch_CoreSearchController();
        $search->register_default_controller($__vBSphinxSearch_CoreSearchController);

        self::_init_index_map();
    }

    protected static function _init_index_map()
    {
        // init index map
        $vb_types = vB_Types::instance();
        self::$_sphinx_index_map = array(
            $vb_types->getContentTypeId('vBForum_Post') => array('ThreadPostMain', 'ThreadPostDelta'),

            // $vb_types->getContentTypeId('vBForum_Forum') => array('ForumMain', 'ForumDelta'),
            $vb_types->getContentTypeId('vBForum_SocialGroupMessage') => array('DiscussionMessageMain', 'DiscussionMessageDelta'),
            $vb_types->getContentTypeId('vBForum_SocialGroup') => array('SocialGroupMain', 'SocialGroupDelta'),
            $vb_types->getContentTypeId('vBBlog_BlogEntry') => array('BlogEntryMain', 'BlogEntryDelta'),
            $vb_types->getContentTypeId('vBBlog_BlogComment') => array('BlogCommentMain', 'BlogCommentDelta'),
            $vb_types->getContentTypeId('vBCms_Article') => array('CMSArticlesMain', 'CMSArticlesDelta'),

            // $vb_types->getContentTypeId('vBCms_StaticHtml') => array('CMSStaticHTMLMain'),
            $vb_types->getContentTypeId('vBForum_VisitorMessage') => array('VisitorMessageMain', 'VisitorMessageDelta'),
        );
        return true;
    }

    public static function get_sphinx_index_map($content_type_id = null)
    {
        if (is_null($content_type_id))
        {
            return self::$_sphinx_index_map;
        }
        $result = array();
        if (array_key_exists($content_type_id, self::$_sphinx_index_map))
        {
            $result = self::$_sphinx_index_map[$content_type_id];
        }
        return $result;
    }

    public static function get_sphinxql_conection()
    {
        global $vbulletin;

        if (!self::$_sphinx_conection OR !mysql_ping(self::$_sphinx_conection))
        {
            $host = $vbulletin->config['sphinx']['sql_host'];
            if ( $host[0] == '/')
            {
                $connection_string =  'localhost:' . $host;
            }
            else
            {
                $port = $vbulletin->config['sphinx']['sql_port'];
                $connection_string = "$host:$port";
            }
            self::$_sphinx_conection = @mysql_connect($connection_string);
        }
        return self::$_sphinx_conection;
    }

    public static function get_results_limit()
    {
        global $vbulletin;
        $limit = self::SPH_DEFAULT_RESULTS_LIMIT;
        if (0 < $vbulletin->options['maxresults'])
        {
            $limit = (int) $vbulletin->options['maxresults'];
        }
        return $limit;
    }

    public static function log_errors($message)
    {
        error_log($err_message); // Send to PHP error log
        require_once(DIR . '/includes/functions_log_error.php');
        log_vbulletin_error($message, 'php'); // Send to vB error log
    }

}

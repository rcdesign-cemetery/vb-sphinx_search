<?php
/**
 * All search requests processed here, in single file
 */
if (!defined('VB_ENTRY'))
    die('Access denied.');

require_once(DIR . '/vb/search/searchcontroller.php');
require_once(DIR . '/vb/search/core.php');

/**
 * Search controller for sphinx search engine.
 * Process all content types.
 *
 * Note: Idea to extend thread search controller failed. Thanks vBulletion team.
 */
class vBSphinxSearch_CoreSearchController extends vB_Search_SearchController
{
    const SIMILAR_THREAD_LIMIT = 5;

    // Intermediate variables.
    // Used to convert input request to sphinx query
    protected $_sphinx_filters = array('deleted = 0');
    protected $_sphinx_index_list;
    protected $_require_grouping = true;
    protected $_tag_search = false;
    protected $_sort = null;
    protected $_direction = null;
    protected $_single_index_enabled = false;
    protected $_limit = null;
    protected $_options = array();
    // Dirty hack. When we sort by sql_attr_str2ordinal field, we
    // can't merge delta index. Define here, when exclude it.
    protected $_sort_fields_with_single_index = array(
        'grouptitlesort',
        'usernamesort',
    );
    // Used ONLY for error logs, not needed anywhere else
    protected $_user_query_text = '';

    /**
     * Map _sort_ attributes: input var name => sphinx field name
     * That's because we use unified index structure for all content types
     */
    protected $_sort_map = array(
        'user' => 'usernamesort',
        'defaultuser' => 'usernamesort',
        'groupuser' => 'usernamesort',
        'title' => 'grouptitlesort',
        'defaulttitle' => 'grouptitlesort',
        'defaultdateline' => 'groupdateline',
        'defaultuserid' => 'userid',
        'dateline' => 'dateline',
        'groupdateline' => 'groupdateline',
        'threadstart' => 'groupdateline',
        'forum' => 'groupparentid',
        'groupreplycount' => 'replycount',
        'replycount' => 'replycount',
        'groupviews' => 'views',
        'views' => 'views',
        'bglastcomment' => 'groupdateline',
        'rank' => '@weight',
        'relevance' => '@weight'
    );
    
    /**
     * Map _search_ attributes: input var name => sphinx field name
     * That's because we use unified index structure for all content types
     */
    protected $_field_map = array(
        'contenttype' => 'contenttypeid',
        'defaultuser' => 'groupuserid',
        'defaultdateline' => 'groupdateline',
        'user' => 'userid',
        'groupuser' => 'groupuserid',
        'dateline' => 'dateline',
        'groupdateline' => 'groupdateline',
        'forumid' => 'groupparentid',
        'replycount' => 'replycount',
        'groupreplycount' => 'replycount',
        'prefixid' => 'prefixcrc',
        'groupid' => 'groupid',
        'messagegroupid' => 'groupparentid',
        'sgcategory' => 'socialgroupcategoryid',
        'sgmemberlimit' => 'members',
        'sgmessagelimit' => 'messages',
        'sgpicturelimit' => 'pictures',
        'sgdiscussionlimit' => 'discussions',
        /* Forums description search disabled */
//		'forumthreadlimit' => 'threadcount',
//		'forumpostlimit' => 'replycount',
//		'forumpostdateline' => 'defaultdateline',
    );

    protected function add_error($error, $query = NULL)
    {
        $message = "Sphinx error \n";
        $message .= "User query string: $this->_user_query_text \n";
        if (!is_null($query))
        {
            $message .= $query . " \n";
        }
        $message .=" --> " . $error . "\n";
        vBSphinxSearch_Core::log_errors($message);
        parent::add_error($message);
    }

    /**
     * Added for compatibility with original search engine
     * Since not used there, leave it empty.
     */
    public function get_supported_filters($contenttype)
    {
        return false;
    }

    /**
     * Added for compatibility with original search engine
     * Since not used there, leave it empty.
     */
    public function get_supported_sorts($contenttype)
    {
        return false;
    }

    /**
     * Added for compatibility with original search engine
     * Seems to not used anywhere.
     * 
     * Cleanup search params, if we plan to reuse dirty class
     */
    public function clear()
    {
        $this->_sphinx_filters = array('deleted = 0');
        $this->_sphinx_index_list;
        $this->_require_grouping = true;
        $this->_tag_search = false;
        $this->_sort = null;
        $this->_direction = null;
        $this->_single_index_enabled = false;
        $this->_limit = null;
        $this->_options = array();
        return true;
    }

    /**
     * Search. Main place, where all things go :)
     * Builds SQL query, send request & pre-process result
     *
     * @param vB_Legacy_Current_User $user user requesting the search
     * @param vB_Search_Criteria $criteria search criteria to process
     *
     * @return array result set.
     */
    public function get_results($user, $criteria)
    {
        // prepare MATCH part of query
        $this->_process_keywords($user, $criteria);

        // Result grouping. We can show all found posts, or
        // just the best post for each thread.
        if (vB_Search_Core::GROUP_NO == $criteria->get_grouped())
        {
            $this->_require_grouping = false;
        }

        // handle "equal" filters
        $this->_process_filters($criteria->get_equals_filters(), 'make_equals_filter');
        // handle "not equal" filters
        $this->_process_filters($criteria->get_notequals_filters(), 'make_notequals_filter');
        // handle "range" filters
        $this->_process_filters($criteria->get_range_filters(), 'make_range_filter');

        // handle sorting options
        $sort = $criteria->get_sort();
        $direction = strtolower($criteria->get_sort_direction()) == 'desc' ? 'desc' : 'asc';
        if ($sort)
        {
            $this->_process_sort($sort, $direction);
        }

        // set sphinx-specific limits
        $this->_set_limit();

        $query = $this->_build_query();
        $result = $this->_run_query($query, true);
        return $result;
    }

    /**
     * Make MATCH part of query (keywords + tags)
     *
     * @param vB_Legacy_Current_User $user user requesting the search
     * @param vB_Search_Criteria $criteria search criteria to process
     */
    protected function _process_keywords($user, $criteria)
    {
        $search_text = $this->_get_search_text($user, $criteria);

        if (!empty($search_text))
        {
            $this->_user_query_text = $search_text;
            $search_text = '"' . $this->_prepare_search_text($search_text, false) . '"/1';

            if ($criteria->is_title_only())
            {
                $search_text = '@grouptitle ' . $search_text;
                $this->_sphinx_filters[] = 'isfirst = 1';
            }
            else
            {
                // @keywordtext include text & title (concated in index rule)
                $search_text = '@keywordtext ' . $search_text;
            }
        }

        $tag = $criteria->get_equals_filter('tag');
        if (0 < $tag)
        {
            $this->_tag_search = true;
            $search_text .= ' @taglist ' . $tag;
        }
        
        if (!empty($search_text))
        {
            // MATCH will be at first place in WHERE
            array_unshift($this->_sphinx_filters, "MATCH('$search_text')");
        }
        return true;
    }

    /**
     * Cleanup and escape search text
     *
     */
    protected function _prepare_search_text($search_text, $enable_boolean = false)
    {
		// all to lowercase
        $text = mb_strtolower(trim($search_text));
        
        if (empty($text))
        {
            return '';
        }
        
        // if boolean mode enabled - then escape special chars.
        // if disabled - remove all possible boolead operands.
        if ($enable_boolean)
        {
            $pattern = array('\\', '@', '~', '/');
            $replacement = array('\\\\', '\@', '\~', '\/');
        }
        else
        {
            $pattern = array('\\', '@', '~', '/',
                '(', ')', '|', '"', '!', '&', '\'', '^', '$', '=', '+');
            $replacement = ' ';
        }
        $text = str_replace($pattern, $replacement, $text);
        $text = trim($text);
        if (empty($text))
        {
            return '';
        }
/*
		// Special street magick to play with '-'.
		// Not compatible with quorum
        $pattern = '/([\p{L}\p{Nd}]+)\-([\p{L}\p{Nd}]+)/u';
        $replacement = '(\1\2) | (\1<<\2)';
        $text = preg_replace($pattern, $replacement, $text);
*/
		// !!! Remove if dashes enabled
		// Don't forget to remove in indexer too
        if (false == $enable_boolean)
        {
            $text = str_replace('-', '', $text);
        }
        
        // Escaping data for mysql protocol
        global $vbulletin;
        $text = $vbulletin->db->escape_string($text);

        return $text;
    }

    /**
     * Hack to get original (unparced) text from search request.
     * Because vBulletin transform it in very stupid way.
     * Then fill some dependent variables.
     *
     * The word build up is taken from the socialgroup/blog implementation
     * The natural language hack is from search.php
     *
     * @param vB_Legacy_Current_User $user user requesting the search
     * @param vB_Search_Criteria $criteria search criteria to process
     */
    protected function _get_search_text($user, $criteria)
    {
        $search_text = $criteria->get_raw_keywords();
        //if we are using the raw search text, use the whole string as the display text
        $criteria->set_keyword_display_string('<b><u>' . htmlspecialchars_uni($search_text) . '</u></b>');
        $criteria->set_highlights(array($search_text));

        return trim($search_text);
    }

    /**
     * Handle processing for the equals / range filters
     *
     * @param array $filters an array of "searchfields" => values to process
     * @param array $filter_method string The name of the method to call to create a
     * 		where snippet for this kind of filter (currently equals and range -- not planning
     * 		to add more).  This should be the name of a private method on this class.
     */
    protected function _process_filters($filters, $filter_method)
    {
        foreach ($filters as $field => $value)
        {
			// names mapping to search index fields
            if (!array_key_exists($field, $this->_field_map))
            {
                continue;
            }
            $index_field = $this->_field_map[$field];

            // bits mapping for forumid and messagegroupid
            if ('forumid' == $field OR 'messagegroupid' == $field)
            {
				// catch content type (see upper 'if' condition) 
                if ('forumid' == $field)
                {
                    $content_type_id = vB_Types::instance()->getContentTypeId('vBForum_Post');
                }
                else
                {
                    $content_type_id = vB_Types::instance()->getContentTypeId('vBForum_SocialGroupMessage');
                }
                
                // combine ID with content type,
                if (is_array($value))
                {
                    foreach ($value as &$id)
                    {
                        $id = $id * vBSphinxSearch_Core::SPH_DOC_ID_PACK_MULT + $content_type_id;
                    }

                }
                else
                {
                    $value = $value * vBSphinxSearch_Core::SPH_DOC_ID_PACK_MULT + $content_type_id;
                }
            }
            
            // We get prefix as string but store in intex as int.
            // Should convert it.
            if ('prefixcrc' == $index_field)
            {
                if (is_array($value))
                {
                    foreach ($value as &$elem)
                    {
                        $elem = sprintf("%u", crc32($elem));
                    }
                }
                else
                {
                    $value = sprintf("%u", crc32($value));
                }
            }
            $this->$filter_method($index_field, $value);
        }
    }

    /**
     * Process equal condition from user criteria
     * Note: Prefix need special processing.
     *       $value can be array or simple type.
     * 
     */
    protected function make_equals_filter($field, $value)
    {
		// vBulletin uses 2 separate indexes for threads & posts.
		// We uses single. Substitute vBForum_Thread -> vBForum_Post
        if ('contenttypeid' == $field)
        {
            $key = array_search(vB_Types::instance()->getContentTypeId('vBForum_Thread'), $value);
            if (false !== $key)
            {
                $value[$key] = vB_Types::instance()->getContentTypeId('vBForum_Post');
            }
            $this->_sphinx_index_list = $this->_get_sphinx_indices($value);
        }

        if (is_array($value) AND count($value) > 1)
        {
            $this->_sphinx_filters[] = $field . ' IN (' . implode(', ', $value) . ')';
        }
        else
        {
			if (is_array($value)) {
				$value = current($value);
			}
            $this->_sphinx_filters[] = "$field = $value";
        }
        return true;
    }

    /**
     * Process not equal condition from user criteria
     * Note: Prefix need special processing.
     *       $value can be array or simple type.
     * 
     */
    protected function make_notequals_filter($field, $value)
    {
        if (is_array($value) AND count($value) > 1)
        {
            $this->_sphinx_filters[] = $field . ' NOT IN (' . implode(', ', $value) . ')';
        }
        else
        {
			if (is_array($value)) {
				$value = current($value);
			}
            $this->_sphinx_filters[] = "$field <> $value";
        }

       return true;
    }

    /**
     * Process range conditions
     * Note: $value always array. 
     *       Used for beetwin condition, first element min and second max.
     *
     */
    protected function make_range_filter($field, $values)
    {
        if (!is_null($values[0]) AND !is_null($values[1]))
        {
            $this->_sphinx_filters[] = "($field >= $values[0] AND $field <= $values[1])";
        }
        else if (!is_null($values[0]))
        {
            $this->_sphinx_filters[] = "$field >= $values[0]";
        }
        else if (!is_null($values[1]))
        {
            $this->_sphinx_filters[] = "$field <= $values[1]";
        }
        return true;
    }

    /**
     * Process sort conditions
     * 
     */
    protected function _process_sort($sort = 'relevance', $direction = 'desc')
    {
        $this->_direction = $direction;

        // map vbulletin terms to sphinx ones
        if (array_key_exists($sort, $this->_sort_map))
        {
            $this->_sort = $this->_sort_map[$sort];
        }
        else
        {
            $this->add_error('Invalid sort condition: ' . $sort);
            global $vbulletin;
            eval(standard_error(fetch_error('sph_invalid_query', $vbulletin->options['contactuslink'])));
        }
        // Disable delta index if attr_str_ordered used
        if (in_array($this->_sort, $this->_sort_fields_with_single_index))
        {
            $this->_single_index_enabled = true;
        }
        return true;
    }

    /**
     * Build (not execute) search query, using prepared conditions
     *
     */
    protected function _build_query()
    {
        global $vbulletin;

        // get all avaliable indexes if search without specific content types
        if (empty($this->_sphinx_index_list))
        {
            $this->_sphinx_index_list = $this->_get_sphinx_indices();
        }

        $query = 'SELECT *';

        if ($this->_require_grouping)
        {
            $query .= ', groupid * ' . vBSphinxSearch_Core::SPH_DOC_ID_PACK_MULT . ' + contenttypeid AS gkey';
        }

        $query .= "\n FROM \n" . $this->_sphinx_index_list;

        if (!empty($this->_sphinx_filters))
        {
            $query .= "\n WHERE \n" . implode(' AND ', $this->_sphinx_filters);
        }

        if ($this->_require_grouping)
        {
            $query .= "\n GROUP BY gkey";
        }

        if (!empty($this->_sort))
        {
            $query .= "\n ORDER BY " . $this->_sort . ' ' . $this->_direction;
        }
        
        // That's not from vB params! Default results count is 500.
        // Due to stupid vB search metod - it want all results at once,
        // don't support paging.
        if ($this->_limit)
        {
            $query .= "\n LIMIT " . $this->_limit;
        }
        
        // Used when limit set & for future extentions (weights, rankers etc.)
        if ($this->_options)
        {
            $query .= "\n OPTION " . implode(', ', $this->_options);
        }
        return $query;
    }

    /**
     * Run query & transform result to vBulletin format
     * 
     * Note: Blogs have unique result format. I think, system architect
     * should die or stop prorgamming.
     *
     */
    protected function _run_query($query, $show_errors = false)
    {
        // Hack to fix blog search result (part 1)
        // Big brother will look for this content types in result,
        // to update data
        $blog_content_type_ids = array(
            vB_Types::instance()->getContentTypeId('vBBlog_BlogEntry'),
            vB_Types::instance()->getContentTypeId('vBBlog_BlogComment'));

        // Connect to sphinx & get result
        for ($i = 0; $i < vBSphinxSearch_Core::SPH_RECONNECT_LIMIT; $i++)
        {
            $con = vBSphinxSearch_Core::get_sphinxql_conection();
            if (false != $con)
            {
                $result_res = mysql_query($query, $con);
                if ($result_res)
                {
                    while ($docinfo = mysql_fetch_assoc($result_res))
                    {
                        unset($row);
                        
                        /* Hack to update blog results (uses different format, part 2)
                         * 
                         * blog row content:
                         * 
                         *   contenttypeid,
                         *   primaryid,
                         *   groupid,
                         *   empty_string
                         * 
                         * other results:
                         * 
                         *   contenttypeid,
                         *   groupid,
                         *   empty_string
                         * 
                         * For blog, contenttypeid should be always Blog Entry, because
                         * View does not support comments display as 'posts'
                         * 
                         */
                        if (in_array($docinfo['contenttypeid'], $blog_content_type_ids))
                        {
                            $row[] = $blog_content_type_ids[0];
                        }
                        else
                        {
                            $row[] = $docinfo['contenttypeid'];
                            $row[] = $docinfo['primaryid'];
                        }
                        
                        $row[] = $docinfo['groupid'];
                        $row[] = '';
                        
                        $result[] = $row;
                    }
                    return $result;
                }
            }
        }
        
        $this->add_error(mysql_error(), $query);
        if ($show_errors)
        {
            $error_message_id = 'sph_invalid_query';
            if (vBSphinxSearch_Core::SPH_CONNECTION_ERROR_NO == mysql_errno())
            {
                $error_message_id = 'sph_connection_error';
            }
            global $vbulletin;
            eval(standard_error(fetch_error($error_message_id, $vbulletin->options['contactuslink'])));
        }
        
        return array();
    }

    /**
     * Get similar threads. Match only first post titles, have specific result limit
     *
     */
    public function get_similar_threads($threadtitle, $threadid = 0)
    {
        global $vbulletin;
        $similarthreads = array();
        $this->_sphinx_index_list = $this->_get_sphinx_indices('vBForum_Post');

        $this->_user_query_text = $threadtitle;
        $search_text = $this->_prepare_search_text($threadtitle, false);

        $this->_sphinx_filters[] = 'isfirst = 1';
        $this->_sphinx_filters[] = "MATCH('@grouptitle \"$search_text\"/1')";
        $this->_sphinx_filters[] = 'contenttypeid = ' . vB_Types::instance()->getContentTypeId('vBForum_Post');

        if (0 < (int)$vbulletin->options['sph_similar_threads_time_line'])
        {
            $time_line = TIMENOW - $vbulletin->options['sph_similar_threads_time_line'] * 24 * 60 * 60;
            $this->_sphinx_filters[] = 'groupdateline >= ' . $time_line;
        }
        $this->_sphinx_filters[] = 'groupvisible = 1';
        if (0 < (int)$threadid)
        {
            $this->_sphinx_filters[] = 'groupid <> ' . $threadid;
        }
        

        $this->_process_sort('relevance');

        $this->_set_limit(self::SIMILAR_THREAD_LIMIT);

        $query = $this->_build_query();
        $result = $this->_run_query($query);
        if (!empty($result))
        {
            $result = array_slice($result, 0, self::SIMILAR_THREAD_LIMIT);
            foreach ($result as $row)
            {
                $similarthreads[] = $row[2];
            }
        }
        return $similarthreads;
    }

    /**
     * Get list of index for current query.
     * Also taken into account taggable of content type(if search by tag)
     *
     */
    protected function _get_sphinx_indices($content_types=null)
    {
        $indexes = array();

        if (!empty($content_types) AND !is_array($content_types))
        {
            $content_types = array(vB_Types::instance()->getContentTypeId($content_types));
        }
        elseif (empty($content_types))
        {
            // hack. Result will by show as post,
            // but post from one thread will be grouped
            $this->_require_grouping = true;
        }
        $searchable_contenttypes = $this->_fetch_content_types('searchable');
        if (is_array($content_types) && count($content_types) > 0)
        {
            $content_types = array_intersect($searchable_contenttypes, $content_types);
        }
        else
        {
            $content_types = $searchable_contenttypes;
        }

        if ($this->_tag_search)
        {
            // Select only the taggable types
            $taggable_contenttypes = $this->_fetch_content_types('taggable');
            $content_types = array_intersect($taggable_contenttypes, $content_types);
        }

        foreach ($content_types as $type)
        {
            $sphinx_index = vBSphinxSearch_Core::get_sphinx_index_map($type);
            if (!empty($sphinx_index))
            {
                if ($this->_single_index_enabled)
                {
                    $indexes[] = $sphinx_index[0];
                }
                else
                {
                    $indexes[] = implode(",", $sphinx_index);
                }
            }
        }

        return implode(", ", $indexes);
    }

    /**
     * Fetch content type by filter.
     * In this script used only "Searchable" and "Taggable"
     *
     */
    protected function _fetch_content_types($filterName = 'Searchable')
    {
        $collection = new vB_Collection_ContentType();
        $filter = 'filter' . ucfirst($filterName);
        $collection->$filter(true);

        $content_types = array();
        foreach ($collection AS $type)
        {
            if ($type->getID() == vB_Types::instance()->getContentTypeId('vBForum_Thread'))
            {
                $content_types[] = vB_Types::instance()->getContentTypeId('vBForum_Post');
            }
            else
            {
                $content_types[] = $type->getID();
            }
        }
        return $content_types;
    }

    /**
     * Set result limit.
     * Note: Set max_mathes option because default value is 500 
     *
     */
    protected function _set_limit($limit = null)
    {
        if (is_null($limit))
        {
            $limit = vBSphinxSearch_Core::get_results_limit();
        }
        $this->_limit = $limit;
        $this->_options[] = 'max_matches=' . $this->_limit;
    }

}

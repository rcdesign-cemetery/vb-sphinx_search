#############################################################################
## data source definition
#############################################################################

source Xmlpipe2Source
{
	type = xmlpipe
    xmlpipe_fixup_utf8 = 1
}

source ForumMainSource: Xmlpipe2Source
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_forum_main "{vbulletin_root_path}"
}

source ForumDeltaSource: ForumMainSource
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_forum_delta "{vbulletin_root_path}"
}

source ThreadPostMainSource: Xmlpipe2Source
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_threadpost_main "{vbulletin_root_path}"
}

source ThreadPostDeltaSource: ThreadPostMainSource
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_threadpost_delta "{vbulletin_root_path}"
}

source DiscussionMessageMainSource: Xmlpipe2Source
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_discussion_message_main "{vbulletin_root_path}"
}

source DiscussionMessageDeltaSource: DiscussionMessageMainSource
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_discussion_message_delta "{vbulletin_root_path}"
}

source SocialGroupMainSource: Xmlpipe2Source
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_social_group_main "{vbulletin_root_path}"
}

source SocialGroupDeltaSource: SocialGroupMainSource
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_social_group_delta "{vbulletin_root_path}"
}


source VisitorMessageMainSource: Xmlpipe2Source
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_visitor_message_main "{vbulletin_root_path}"
}


source VisitorMessageDeltaSource: VisitorMessageMainSource
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_visitor_message_delta "{vbulletin_root_path}"
}

source BlogEntryMainSource: Xmlpipe2Source
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_blog_entry_main "{vbulletin_root_path}"
}

source BlogEntryDeltaSource: BlogEntryMainSource
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_blog_entry_delta "{vbulletin_root_path}"
}

source BlogCommentMainSource: Xmlpipe2Source
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_blog_comment_main "{vbulletin_root_path}"
}

source BlogCommentDeltaSource: BlogCommentMainSource
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_blog_comment_delta "{vbulletin_root_path}"
}

source CMSArticlesMainSource: Xmlpipe2Source
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_cms_articles_main "{vbulletin_root_path}"
}

source CMSArticlesDeltaSource: CMSArticlesMainSource
{
    xmlpipe_command = /usr/local/sphinx/sphinxpipe.php index_cms_articles_delta "{vbulletin_root_path}"
}


#############################################################################
## index definition
#############################################################################

index ForumMain
{
    source			= ForumMainSource
    path			= {index_path}/ForumMain
    docinfo         = extern
    morphology      = stem_enru
    min_stemming_len = 4
    min_word_len    = 1

    charset_type    = utf-8

    charset_table	= 0..9, A..Z->a..z, _, a..z, \
        U+451->U+435, U+401->U+435, U+410..U+42F->U+430..U+44F, U+430..U+44F

    ignore_chars = -, U+AD
	
    {sphinx_stopwords_file}
    {sphinx_wordforms_file}
    {sphinx_exceptions_file}


	preopen = 1
#	inplace_enable = 1
#	inplace_hit_gap = 1M
#	inplace_docinfo_gap = 1M

# Unclear
#
# index_exact_words, expand keyword
# blend_chars

}

index ForumDelta : ForumMain
{
	source			= ForumDeltaSource
	path			= {index_path}/ForumDelta
}

index ThreadPostMain : ForumMain
{
	source			= ThreadPostMainSource
	path			= {index_path}/ThreadPostMain
}

index ThreadPostDelta : ForumMain
{
	source			= ThreadPostDeltaSource
	path			= {index_path}/ThreadPostDelta
}

index DiscussionMessageMain : ForumMain
{
	source			= DiscussionMessageMainSource
	path			= {index_path}/DiscussionMessageMain
}

index DiscussionMessageDelta : ForumMain
{
	source			= DiscussionMessageDeltaSource
	path			= {index_path}/DiscussionMessageDelta
}

index SocialGroupMain : ForumMain
{
	source			= SocialGroupMainSource
	path			= {index_path}/SocialGroupMain
}

index SocialGroupDelta : ForumMain
{
	source			= SocialGroupDeltaSource
	path			= {index_path}/SocialGroupDelta
}


index VisitorMessageMain : ForumMain
{
	source			= VisitorMessageMainSource
	path			= {index_path}/VisitorMessageMain
}

index VisitorMessageDelta : ForumMain
{
	source			= VisitorMessageDeltaSource
	path			= {index_path}/VisitorMessageDelta
}

index BlogEntryMain : ForumMain
{
	source			= BlogEntryMainSource
	path			= {index_path}/BlogEntryMain
}

index BlogEntryDelta : ForumMain
{
	source			= BlogEntryDeltaSource
	path			= {index_path}/BlogEntryDelta
}

index BlogCommentMain : ForumMain
{
	source			= BlogCommentMainSource
	path			= {index_path}/BlogCommentMain
}

index BlogCommentDelta : ForumMain
{
	source			= BlogCommentDeltaSource
	path			= {index_path}/BlogCommentDelta
}

index CMSArticlesMain : ForumMain
{
	source			= CMSArticlesMainSource
	path			= {index_path}/CMSArticlesMain
}


index CMSArticlesDelta : ForumMain
{
	source			= CMSArticlesDeltaSource
	path			= {index_path}/CMSArticlesDelta
}

#############################################################################
## indexer settings
#############################################################################

indexer
{
	mem_limit			= {mem_limit}M
}

#############################################################################
## searchd settings
#############################################################################

searchd
{
	##########
   
    # sockets 
    listen = {sphinx_ql}:mysql41

    # log file
	# searchd run info is logged here
    log = {log_file}

	# query log file
    # all the search queries are logged here
    query_log = {query_log}

    # client read timeout, seconds
	read_timeout = {read_timeout}

    # maximum amount of children to fork
    # useful to control server load
    max_children = {max_children}


    # a file which will contain searchd process ID
    # used for different external automation scripts
    # MUST be present
    pid_file = {searchd_pid}

    # maximum amount of matches this daemon would retrieve from each index
    # and serve to client
    #
    # this parameter affects per-client memory usage slightly (16 bytes per match)
    # and CPU usage in match sorting phase; so blindly raising it to 1 million
    # is definitely NOT recommended
    #
    # default is 1000 (just like with Google)
	max_matches = {max_matches}
}

# --eof--



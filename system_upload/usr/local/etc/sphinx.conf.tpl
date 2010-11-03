#############################################################################
## data source definition
#############################################################################

source DBSource
{
	type					= mysql

	#####################################################################
	## SQL settings (for 'mysql' and 'pgsql' types)
	#####################################################################

    sql_host        = {db_host}
    sql_user        = {db_user}
    sql_pass        = {db_pass}
    sql_db          = {db_name}
    {db_sock}
    {db_port}

}

source ForumMainSource : DBSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

    sql_query_pre = \
        UPDATE \
            {table_prefix}vbsphinxsearch_queue \
        SET \
            `done` = '1' \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'Forum')

	sql_query 				= \
		SELECT \
			(f.forumid*64 + c.contenttypeid) AS id, \
			c.contenttypeid AS contenttypeid, \
			f.forumid AS groupid, \
			f.forumid AS primaryid, \
			f.lastpost AS dateline, \
			f.lastpost AS groupdateline, \
			f.title AS grouptitle, \
			0 AS userid, \
			0 AS defaultuserid, \
			f.description AS keywordtext, \
			IF(f.displayorder <= 0, 0, 1) AS visible, \
			f.threadcount AS threadcount, \
			f.replycount AS replycount \
		FROM {table_prefix}forum f \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'Forum' \
			AND forumid>=$start AND forumid<=$end

    sql_query_post_index = \
        DELETE FROM \
            {table_prefix}vbsphinxsearch_queue \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid \
                    FROM {table_prefix}contenttype \
                    WHERE class = 'Forum') AND \
            done=1;
    sql_query_post_index = \
        REPLACE INTO \
            {table_prefix}vbsphinxsearch_counters ( contenttypeid, maxprimaryid ) \
        SELECT \
            contenttypeid, (($maxid -  contenttypeid)/64) \
        FROM \
            {table_prefix}contenttype \
        WHERE \
            class = 'Forum'

    sql_query_killlist = \
        SELECT \
            ((sq.primaryid )*64 + sq.contenttypeid) AS id \
        FROM \
            {table_prefix}vbsphinxsearch_queue AS sq \
        LEFT JOIN {table_prefix}vbsphinxsearch_counters AS sc ON \
			sq.contenttypeid = sc.contenttypeid \
        WHERE \
            sq.contenttypeid = (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'Forum') AND \
            sq.primaryid <= sc.maxprimaryid

	
	sql_attr_uint			= contenttypeid
	sql_attr_uint			= groupid
	sql_attr_uint			= primaryid
	sql_attr_timestamp		= dateline
	sql_attr_timestamp		= groupdateline
	sql_attr_uint			= userid
	sql_attr_uint			= defaultuserid
	sql_attr_uint			= visible
	sql_attr_uint			= threadcount
	sql_attr_uint			= replycount
	
	sql_range_step		= 1024
	sql_query_range = SELECT MIN(forumid),MAX(forumid) FROM {table_prefix}forum
	
	sql_attr_multi = uint tagid from query; SELECT 0, 0
}

source ForumDeltaSource: ForumMainSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

	sql_query 				= \
		SELECT \
			(f.forumid*64 + c.contenttypeid) AS id, \
			c.contenttypeid AS contenttypeid, \
			f.forumid AS groupid, \
			f.forumid AS primaryid, \
			f.lastpost AS dateline, \
			f.lastpost AS groupdateline, \
			f.title AS grouptitle, \
			0 AS userid, \
			0 AS defaultuserid, \
			f.description AS keywordtext, \
			IF(f.displayorder <= 0, 0, 1) AS visible, \
			f.threadcount AS threadcount, \
			f.replycount AS replycount \
		FROM {table_prefix}forum f \
		JOIN {table_prefix}vbsphinxsearch_queue sq ON \
			(sq.primaryid = f.forumid AND  sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'Forum')) \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'Forum' \
			AND forumid>=$start AND forumid<=$end

    sql_query_post_index = SELECT 1;
}

source ThreadPostMainSource : DBSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

    sql_query_pre = \
        UPDATE \
            {table_prefix}vbsphinxsearch_queue \
        SET \
            `done` = '1' \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'Post')

	sql_query 				= \
		SELECT \
			(p.postid*64 + c.contenttypeid) AS id, \
			c.contenttypeid AS contenttypeid, \
			p.threadid AS groupid, \
			p.postid AS primaryid, \
			p.dateline AS dateline, \
    	    t.lastpost AS groupdateline, \
			t.title AS grouptitle, \
			p.userid AS userid, \
			t.postuserid AS groupuserid, \
			CONCAT(IFNULL(p.title, ''),' ', IFNULL(p.pagetext, '')) AS keywordtext, \
    	    CRC32( t.prefixid ) AS prefixcrc, \
			p.visible AS visible, \
			t.replycount AS replycount, \
			IF(t.views<=t.replycount, t.replycount+1, t.views) AS views, \
			t.dateline AS groupstart, \
			t.visible AS groupvisible, \
			t.open AS groupopen, \
			t.forumid AS groupparentid, \
    	    0 AS deleted, \
            IF(p.parentid =0, 1, 0 ) AS isfirst, \
            t.title AS grouptitlesort, \
            p.username AS usernamesort, \
            t.postusername AS groupusernamesort \
		FROM {table_prefix}post AS p \
		JOIN {table_prefix}thread AS t ON \
			p.threadid = t.threadid \
		JOIN {table_prefix}contenttype AS c \
        WHERE \
			c.class = 'Post' \
			AND p.postid>=$start AND p.postid<=$end

	sql_query_range         = SELECT MIN(postid),MAX(postid) FROM {table_prefix}post
	sql_range_step		= 1024

    sql_query_post_index = \
        DELETE FROM \
            {table_prefix}vbsphinxsearch_queue \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid \
                    FROM {table_prefix}contenttype \
                    WHERE class = 'Post') AND \
            done=1;

    sql_query_post_index = \
        REPLACE INTO \
            {table_prefix}vbsphinxsearch_counters ( contenttypeid, maxprimaryid ) \
        SELECT \
            contenttypeid, (($maxid -  contenttypeid)/64) \
        FROM \
            {table_prefix}contenttype \
        WHERE \
            class = 'Post'

    sql_query_killlist = \
        SELECT \
            ((sq.primaryid )*64 + sq.contenttypeid) AS id \
        FROM \
            {table_prefix}vbsphinxsearch_queue AS sq \
        LEFT JOIN {table_prefix}vbsphinxsearch_counters AS sc ON \
			sq.contenttypeid = sc.contenttypeid \
        WHERE \
            sq.contenttypeid = (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'Post') AND \
            sq.primaryid <= sc.maxprimaryid

	
	sql_attr_uint			= contenttypeid
	sql_attr_uint			= groupid
	sql_attr_uint			= primaryid
	sql_attr_timestamp		= dateline
	sql_attr_timestamp		= groupdateline
	sql_attr_uint			= userid
	sql_attr_uint			= groupuserid
	sql_attr_uint			= prefixcrc
	sql_attr_uint			= visible
	sql_attr_uint			= replycount
	sql_attr_uint			= views
	sql_attr_timestamp		= groupstart
	sql_attr_uint			= groupvisible
	sql_attr_uint			= groupopen
	sql_attr_uint			= groupparentid
	sql_attr_bool           = deleted
    sql_attr_bool           = isfirst
    sql_attr_str2ordinal    = grouptitlesort
    sql_attr_str2ordinal    = usernamesort
    sql_attr_str2ordinal    = groupusernamesort
	
	sql_attr_multi = uint tagid from query; SELECT ((p.postid)*64 + (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'Post')) AS id, t.tagid FROM {table_prefix}tagcontent t JOIN {table_prefix}contenttype c ON (t.contenttypeid = c.contenttypeid AND c.class = 'Thread') JOIN {table_prefix}post p ON (p.threadid = t.contentid)
	
	
}

source ThreadPostDeltaSource: ThreadPostMainSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

    sql_query 				= \
		SELECT (p.postid*64 + c.contenttypeid) AS id, \
			c.contenttypeid AS contenttypeid, \
			p.threadid AS groupid, \
			p.postid AS primaryid, \
			p.dateline AS dateline, \
    	    t.lastpost AS groupdateline, \
			t.title AS grouptitle, \
			p.userid AS userid, \
			t.postuserid AS groupuserid, \
			CONCAT(IFNULL(p.title, ''),' ', IFNULL(p.pagetext, '')) AS keywordtext, \
    	    CRC32( t.prefixid ) AS prefixcrc, \
			p.visible AS visible, \
			t.replycount AS replycount, \
			IF(t.views<=t.replycount, t.replycount+1, t.views) AS views, \
			t.dateline AS groupstart, \
			t.visible AS groupvisible, \
			t.open AS groupopen, \
			t.forumid AS groupparentid, \
    	    0 AS deleted, \
            IF(p.parentid =0, 1, 0 ) AS isfirst, \
            t.title AS grouptitlesort, \
            p.username AS usernamesort, \
            t.postusername AS groupusernamesort \
		FROM {table_prefix}post AS p \
		JOIN {table_prefix}vbsphinxsearch_queue sq ON \
            sq.primaryid = p.postid AND \
            sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'Post') \
		JOIN {table_prefix}thread AS t ON \
			p.threadid = t.threadid \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'Post' \
			AND p.postid>=$start AND p.postid<=$end
		
	sql_attr_multi = uint tagid from query; SELECT ((p.postid)*64 + (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'Post')) AS id, t.tagid FROM {table_prefix}tagcontent t JOIN {table_prefix}contenttype c ON (t.contenttypeid = c.contenttypeid AND c.class = 'Thread') JOIN {table_prefix}vbsphinxsearch_queue sq ON (sq.primaryid = t.contentid AND sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'Thread')) JOIN {table_prefix}post p ON (p.threadid = t.contentid)
			
    sql_query_post_index = SELECT 1;
}

source DiscussionMessageMainSource: DBSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

    sql_query_pre = \
        UPDATE \
            {table_prefix}vbsphinxsearch_queue \
        SET \
            `done` = '1' \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid \
                    FROM {table_prefix}contenttype \
                    WHERE class = 'SocialGroupMessage')

	sql_query 				= \
		SELECT \
			(gm.gmid*64 + c.contenttypeid) AS id, \
			c.contenttypeid AS contenttypeid, \
			gm.discussionid AS groupid, \
			gm.gmid AS primaryid, \
			gm.dateline AS dateline, \
			gm.dateline AS groupdateline, \
			gm.postuserid AS userid, \
			fp.postuserid AS groupuserid, \
			fp.title AS grouptitle, \
			gm.pagetext AS keywordtext, \
			IF(gm.state = 'visible', 1, 0) AS visible, \
			d.groupid AS groupparentid, \
            0 AS deleted, \
            IF(gm.gmid = d.firstpostid, 1, 0 ) AS isfirst, \
            fp.title AS grouptitlesort, \
            gm.postusername AS usernamesort, \
            fp.postusername AS groupusernamesort \
		FROM {table_prefix}groupmessage AS gm \
		JOIN {table_prefix}discussion AS d ON \
			(gm.discussionid = d.discussionid) \
		JOIN {table_prefix}groupmessage AS fp ON \
			(d.firstpostid = fp.gmid) \
		JOIN {table_prefix}contenttype c \
		WHERE \
			c.class = 'SocialGroupMessage' \
			AND gm.gmid>=$start AND gm.gmid<=$end
	

	sql_range_step		= 1024
	sql_query_range		= SELECT MIN(gmid),MAX(gmid) FROM {table_prefix}groupmessage

    sql_query_post_index = \
        DELETE FROM \
            {table_prefix}vbsphinxsearch_queue \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid \
                    FROM {table_prefix}contenttype \
                    WHERE class = 'SocialGroupMessage') AND \
            done=1;

    sql_query_post_index = \
        REPLACE INTO \
            {table_prefix}vbsphinxsearch_counters ( contenttypeid, maxprimaryid ) \
        SELECT \
            contenttypeid, (($maxid -  contenttypeid)/64) \
        FROM \
            {table_prefix}contenttype \
        WHERE \
            class = 'SocialGroupMessage'

    sql_query_killlist = \
        SELECT \
            ((sq.primaryid )*64 + sq.contenttypeid) AS id \
        FROM \
            {table_prefix}vbsphinxsearch_queue AS sq \
        LEFT JOIN {table_prefix}vbsphinxsearch_counters AS sc ON \
			sq.contenttypeid = sc.contenttypeid \
        WHERE \
            sq.contenttypeid = (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'SocialGroupMessage') AND \
            sq.primaryid <= sc.maxprimaryid

	sql_attr_uint			= contenttypeid
	sql_attr_uint			= groupid
	sql_attr_uint			= primaryid
	sql_attr_timestamp		= dateline
	sql_attr_timestamp		= groupdateline
	sql_attr_uint			= userid
	sql_attr_uint			= groupuserid
	sql_attr_uint			= visible
	sql_attr_uint			= groupparentid
    sql_attr_bool           = deleted
    sql_attr_bool           = isfirst
    sql_attr_str2ordinal    = grouptitlesort
    sql_attr_str2ordinal    = usernamesort
    sql_attr_str2ordinal    = groupusernamesort
	
	sql_attr_multi = uint tagid from query; SELECT 0, 0
			
}

source DiscussionMessageDeltaSource: DiscussionMessageMainSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

	sql_query 				= \
		SELECT (gm.gmid*64 + c.contenttypeid) AS id, \
			5 AS contenttypeid, \
			gm.discussionid AS groupid, \
			gm.gmid AS primaryid, \
			gm.dateline AS dateline, \
			gm.dateline AS groupdateline, \
			gm.postuserid AS userid, \
			fp.postuserid AS groupuserid, \
			fp.title AS grouptitle, \
			gm.pagetext AS keywordtext, \
			IF(gm.state = 'visible', 1, 0) AS visible, \
			d.groupid AS groupparentid, \
            0 AS deleted, \
            IF(gm.gmid = d.firstpostid, 1, 0 ) AS isfirst, \
            fp.title AS grouptitlesort, \
            gm.postusername AS usernamesort, \
            fp.postusername AS groupusernamesort \
        FROM {table_prefix}groupmessage AS gm \
		JOIN {table_prefix}vbsphinxsearch_queue sq ON ( \
				(sq.primaryid = gm.gmid AND sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'SocialGroupMessage')) \
				OR \
				(sq.primaryid = gm.discussionid AND sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'SocialGroupDiscussion')) \
			) \
		JOIN {table_prefix}discussion AS d ON \
			(gm.discussionid = d.discussionid) \
		JOIN {table_prefix}groupmessage AS fp ON \
			(d.firstpostid = fp.gmid) \
		JOIN {table_prefix}contenttype c \
		WHERE \
			c.class = 'SocialGroupMessage' \
			AND gm.gmid>=$start AND gm.gmid<=$end

    sql_query_post_index = SELECT 1;
}

source SocialGroupMainSource : DBSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

    sql_query_pre = \
        UPDATE \
            {table_prefix}vbsphinxsearch_queue \
        SET \
            `done` = '1' \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'SocialGroup')

	sql_query 				= \
		SELECT \
			(sg.groupid*64 + c.contenttypeid) AS id, \
			sg.groupid AS groupid, \
			c.contenttypeid AS contenttypeid, \
			sg.groupid AS primaryid, \
			sg.dateline AS dateline, \
			sg.lastpost AS groupdateline, \
			sg.name AS grouptitle, \
			sg.creatoruserid AS userid, \
			sg.description AS keywordtext, \
			IF(sg.visible <= 0, 0, 1) AS visible, \
			sg.members AS members, \
			sg.discussions AS discussions, \
			sg.visible AS messages, \
			sg.picturecount AS pictures, \
			sg.socialgroupcategoryid AS socialgroupcategoryid, \
            0 AS deleted, \
            0 AS isfirst, \
            sg.name AS grouptitlesort, \
            u.username AS usernamesort, \
            u.username AS groupusernamesort \
		FROM {table_prefix}socialgroup AS sg \
		JOIN {table_prefix}user AS u ON \
			sg.creatoruserid = u.userid \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'SocialGroup' \
			AND sg.groupid>=$start AND sg.groupid<=$end

    sql_query_post_index = \
        DELETE FROM \
            {table_prefix}vbsphinxsearch_queue \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid \
                    FROM {table_prefix}contenttype \
                    WHERE class = 'SocialGroup') AND \
            done=1;

	sql_query_range		= SELECT MIN(groupid),MAX(groupid) FROM {table_prefix}socialgroup
	sql_range_step      = 1024

    sql_query_post_index = \
        REPLACE INTO \
            {table_prefix}vbsphinxsearch_counters ( contenttypeid, maxprimaryid ) \
        SELECT \
            contenttypeid, (($maxid -  contenttypeid)/64) \
        FROM \
            {table_prefix}contenttype \
        WHERE \
            class = 'SocialGroup'

    sql_query_killlist = \
        SELECT \
            ((sq.primaryid )*64 + sq.contenttypeid) AS id \
        FROM \
            {table_prefix}vbsphinxsearch_queue AS sq \
        LEFT JOIN {table_prefix}vbsphinxsearch_counters AS sc ON \
			sq.contenttypeid = sc.contenttypeid \
        WHERE \
            sq.contenttypeid = (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'SocialGroup') AND \
            sq.primaryid <= sc.maxprimaryid
		

	sql_attr_uint			= contenttypeid
	sql_attr_uint			= groupid
	sql_attr_uint			= primaryid	
	sql_attr_timestamp		= dateline
	sql_attr_timestamp		= groupdateline
	sql_attr_uint			= userid
	sql_attr_uint			= visible
	sql_attr_uint			= members
	sql_attr_uint			= discussions
	sql_attr_uint			= messages
	sql_attr_uint			= pictures
	sql_attr_uint			= socialgroupcategoryid
    sql_attr_bool           = deleted
    sql_attr_bool           = isfirst
    sql_attr_str2ordinal    = grouptitlesort
    sql_attr_str2ordinal    = usernamesort
    sql_attr_str2ordinal    = groupusernamesort	
	
	sql_attr_multi = uint tagid from query; SELECT 0, 0
	
		
}

source SocialGroupDeltaSource: SocialGroupMainSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

	sql_query 				= \
		SELECT \
			(sg.groupid*64 + c.contenttypeid) AS id, \
			sg.groupid AS groupid, \
			c.contenttypeid AS contenttypeid, \
			sg.groupid AS primaryid, \
			sg.dateline AS dateline, \
			sg.lastpost AS groupdateline, \
			sg.name AS grouptitle, \
			sg.creatoruserid AS userid, \
			sg.description AS keywordtext, \
			IF(sg.visible <= 0, 0, 1) AS visible, \
			sg.members AS members, \
			sg.discussions AS discussions, \
			sg.visible AS messages, \
			sg.picturecount AS pictures, \
			sg.socialgroupcategoryid AS socialgroupcategoryid, \
            0 AS deleted, \
            0 AS isfirst, \
            sg.name AS grouptitlesort, \
            u.username AS usernamesort, \
            u.username AS groupusernamesort \
		FROM {table_prefix}socialgroup AS sg \
		JOIN {table_prefix}user AS u ON \
			sg.creatoruserid = u.userid \
		JOIN {table_prefix}vbsphinxsearch_queue sq ON \
			(sq.primaryid = sg.groupid AND  sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'SocialGroup')) \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'SocialGroup' \
			AND sg.groupid>=$start AND sg.groupid<=$end

    sql_query_post_index = SELECT 1;
}


source VisitorMessageMainSource : DBSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

    sql_query_pre = \
        UPDATE \
            {table_prefix}vbsphinxsearch_queue \
        SET \
            `done` = '1' \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'VisitorMessage')

	sql_query 				= \
		SELECT \
			(vm.vmid*64 + c.contenttypeid) AS id, \
			vm.vmid AS groupid, \
			c.contenttypeid AS contenttypeid, \
			vm.vmid AS primaryid, \
			vm.dateline AS dateline, \
			vm.dateline AS groupdateline, \
			vm.title AS grouptitle, \
			vm.postuserid AS userid, \
			vm.pagetext AS keywordtext, \
			IF(vm.state <> 'visible', 0, 1) AS visible, \
            0 AS deleted, \
            1 AS isfirst, \
            vm.title AS grouptitlesort, \
            vm.postusername AS usernamesort, \
            vm.postusername AS groupusernamesort \
		FROM {table_prefix}visitormessage AS vm \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'VisitorMessage' \
			AND vm.vmid>=$start AND vm.vmid<=$end
		
	sql_query_range		= SELECT MIN(vmid),MAX(vmid) FROM {table_prefix}visitormessage
	sql_range_step      = 1024

    sql_query_post_index = \
        DELETE FROM \
            {table_prefix}vbsphinxsearch_queue \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid \
                    FROM {table_prefix}contenttype \
                    WHERE class = 'VisitorMessage') AND \
            done=1;

    sql_query_post_index = \
        REPLACE INTO \
            {table_prefix}vbsphinxsearch_counters ( contenttypeid, maxprimaryid ) \
        SELECT \
            contenttypeid, (($maxid -  contenttypeid)/64) \
        FROM \
            {table_prefix}contenttype \
        WHERE \
            class = 'VisitorMessage'

    sql_query_killlist = \
        SELECT \
            ((sq.primaryid )*64 + sq.contenttypeid) AS id \
        FROM \
            {table_prefix}vbsphinxsearch_queue AS sq \
        LEFT JOIN {table_prefix}vbsphinxsearch_counters AS sc ON \
			sq.contenttypeid = sc.contenttypeid \
        WHERE \
            sq.contenttypeid = (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'VisitorMessage') AND \
            sq.primaryid <= sc.maxprimaryid

	sql_attr_uint			= contenttypeid
	sql_attr_uint			= groupid
	sql_attr_uint			= primaryid	
	sql_attr_timestamp		= dateline
	sql_attr_timestamp		= groupdateline
	sql_attr_uint			= userid
	sql_attr_uint			= visible
    sql_attr_bool           = deleted
    sql_attr_bool           = isfirst
    sql_attr_str2ordinal    = grouptitlesort
    sql_attr_str2ordinal    = usernamesort
    sql_attr_str2ordinal    = groupusernamesort
	
	sql_attr_multi = uint tagid from query; SELECT 0, 0
	
}


source VisitorMessageDeltaSource: VisitorMessageMainSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

	sql_query 				= \
		SELECT \
			(vm.vmid*64 + c.contenttypeid) AS id, \
			vm.vmid AS groupid, \
			c.contenttypeid AS contenttypeid, \
			vm.vmid AS primaryid, \
			vm.dateline AS dateline, \
			vm.dateline AS groupdateline, \
			vm.title AS grouptitle, \
			vm.postuserid AS userid, \
			vm.pagetext AS keywordtext, \
			IF(vm.state <> 'visible', 0, 1) AS visible, \
            0 AS deleted, \
            1 AS isfirst, \
            vm.title AS grouptitlesort, \
            vm.postusername AS usernamesort, \
            vm.postusername AS groupusernamesort \
		FROM {table_prefix}visitormessage AS vm \
		JOIN {table_prefix}vbsphinxsearch_queue sq ON \
			(sq.primaryid = vm.vmid AND  sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'VisitorMessage')) \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'VisitorMessage' \
			AND vm.vmid>=$start AND vm.vmid<=$end

    sql_query_post_index = SELECT 1;
}

source BlogEntryMainSource : DBSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

    sql_query_pre = \
        UPDATE \
            {table_prefix}vbsphinxsearch_queue \
        SET \
            `done` = '1' \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'BlogEntry')


	sql_query 				= \
		SELECT \
			(bt.blogtextid*64 + c.contenttypeid) AS id, \
			c.contenttypeid AS contenttypeid, \
			bt.blogid AS groupid, \
			bt.blogtextid AS primaryid, \
			bt.dateline AS dateline, \
			b.lastcomment AS groupdateline, \
			bt.title AS grouptitle, \
			bt.bloguserid AS userid, \
			bt.bloguserid AS groupuserid, \
			bt.pagetext AS keywordtext, \
			IF(bt.state = 'visible', 1, 0) AS visible, \
            0 AS deleted, \
            1 AS isfirst, \
            bt.title AS grouptitlesort, \
            bt.username AS usernamesort, \
            bt.username AS groupusernamesort \
		FROM {table_prefix}blog_text AS bt \
        JOIN {table_prefix}blog AS b ON bt.blogtextid = b.firstblogtextid \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'BlogEntry' \
			AND bt.blogtextid>=$start AND bt.blogtextid<=$end
		
	sql_query_range		= SELECT MIN(blogtextid),MAX(blogtextid) FROM {table_prefix}blog_text
		
	sql_range_step      = 1024

    sql_query_post_index = \
        DELETE FROM \
            {table_prefix}vbsphinxsearch_queue \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid \
                    FROM {table_prefix}contenttype \
                    WHERE class = 'BlogEntry') AND \
            done=1;

    sql_query_post_index = \
        REPLACE INTO \
            {table_prefix}vbsphinxsearch_counters ( contenttypeid, maxprimaryid ) \
        SELECT \
            contenttypeid, (($maxid -  contenttypeid)/64) \
        FROM \
            {table_prefix}contenttype \
        WHERE \
            class = 'BlogEntry'

    sql_query_killlist = \
        SELECT \
            ((sq.primaryid )*64 + sq.contenttypeid) AS id \
        FROM \
            {table_prefix}vbsphinxsearch_queue AS sq \
        LEFT JOIN {table_prefix}vbsphinxsearch_counters AS sc ON \
			sq.contenttypeid = sc.contenttypeid \
        WHERE \
            sq.contenttypeid = (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'BlogEntry') AND \
            sq.primaryid <= sc.maxprimaryid

	sql_attr_uint			= contenttypeid
	sql_attr_uint			= groupid
	sql_attr_uint			= primaryid	
	sql_attr_timestamp		= dateline
	sql_attr_timestamp		= groupdateline
	sql_attr_uint			= userid
	sql_attr_uint			= groupuserid
	sql_attr_uint			= visible
    sql_attr_bool           = deleted
    sql_attr_bool           = isfirst
    sql_attr_str2ordinal    = grouptitlesort
    sql_attr_str2ordinal    = usernamesort
    sql_attr_str2ordinal    = groupusernamesort
	
	sql_attr_multi = uint tagid from query; SELECT ((bt.blogtextid)*64 + (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'BlogComment')) AS id, t.tagid FROM {table_prefix}tagcontent t JOIN {table_prefix}contenttype c ON (t.contenttypeid = c.contenttypeid AND c.class = 'BlogEntry') JOIN {table_prefix}blog_text bt ON (bt.blogtextid = t.contentid)
	
}

source BlogEntryDeltaSource: BlogEntryMainSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

	sql_query 				= \
		SELECT \
			(bt.blogtextid*64 + c.contenttypeid) AS id, \
			c.contenttypeid AS contenttypeid, \
			bt.blogid AS groupid, \
			bt.blogtextid AS primaryid, \
			bt.dateline AS dateline, \
			b.lastcomment AS groupdateline, \
			bt.title AS grouptitle, \
			bt.bloguserid AS userid, \
			bt.bloguserid AS groupuserid, \
			bt.pagetext AS keywordtext, \
			IF(bt.state = 'visible', 1, 0) AS visible, \
            0 AS deleted, \
            1 AS isfirst, \
            bt.title AS grouptitlesort, \
            bt.username AS usernamesort, \
            bt.username AS groupusernamesort \
			FROM {table_prefix}blog_text AS bt \
			JOIN {table_prefix}blog AS b ON bt.blogtextid = b.firstblogtextid \
			JOIN {table_prefix}vbsphinxsearch_queue sq ON \
				(sq.primaryid = bt.blogtextid AND  sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'BlogEntry')) \
			JOIN {table_prefix}contenttype AS c \
			WHERE \
				c.class = 'BlogEntry' \
				AND bt.blogtextid>=$start AND bt.blogtextid<=$end

	sql_attr_multi = uint tagid from query; SELECT ((bt.blogtextid)*64 + (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'BlogComment')) AS id, t.tagid FROM {table_prefix}tagcontent t JOIN {table_prefix}contenttype c ON (t.contenttypeid = c.contenttypeid AND c.class = 'BlogEntry') JOIN {table_prefix}vbsphinxsearch_queue sq ON (sq.primaryid = t.contentid AND sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'BlogEntry')) JOIN {table_prefix}blog_text bt ON (bt.blogtextid = t.contentid)
    sql_query_post_index = SELECT 1;
}

source BlogCommentMainSource : DBSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

    sql_query_pre = \
        UPDATE \
            {table_prefix}vbsphinxsearch_queue \
        SET \
            `done` = '1' \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'BlogComment')

	sql_query 				= \
		SELECT \
			(bt.blogtextid*64 + c.contenttypeid) AS id, \
			c.contenttypeid AS contenttypeid, \
			bt.blogid AS groupid, \
			bt.blogtextid AS primaryid, \
			bt.dateline AS dateline, \
			b.lastcomment AS groupdateline, \
			b.title AS grouptitle, \
			bt.bloguserid AS userid, \
			bt.bloguserid AS groupuserid, \
			bt.pagetext AS keywordtext, \
			IF(bt.state = 'visible', 1, 0) AS visible, \
			0 AS deleted, \
            0 AS isfirst, \
            b.title AS grouptitlesort, \
            bt.username AS usernamesort, \
            bt.username AS groupusernamesort \
		FROM {table_prefix}blog_text AS bt \
		JOIN {table_prefix}blog AS b ON \
			(b.blogid = bt.blogid AND b.firstblogtextid != bt.blogtextid) \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'BlogComment' \
			AND bt.blogtextid>=$start AND bt.blogtextid<=$end
		
	sql_query_range		= SELECT MIN(blogtextid),MAX(blogtextid) FROM {table_prefix}blog_text
		
	sql_range_step      = 1024

    sql_query_post_index = \
        DELETE FROM \
            {table_prefix}vbsphinxsearch_queue \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid \
                    FROM {table_prefix}contenttype \
                    WHERE class = 'BlogComment') AND \
            done=1;

    sql_query_post_index = \
        REPLACE INTO \
            {table_prefix}vbsphinxsearch_counters ( contenttypeid, maxprimaryid ) \
        SELECT \
            contenttypeid, (($maxid -  contenttypeid)/64) \
        FROM \
            {table_prefix}contenttype \
        WHERE \
            class = 'BlogComment'

    sql_query_killlist = \
        SELECT \
            ((sq.primaryid )*64 + sq.contenttypeid) AS id \
        FROM \
            {table_prefix}vbsphinxsearch_queue AS sq \
        LEFT JOIN {table_prefix}vbsphinxsearch_counters AS sc ON \
			sq.contenttypeid = sc.contenttypeid \
        WHERE \
            sq.contenttypeid = (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'BlogComment') AND \
            sq.primaryid <= sc.maxprimaryid

	sql_attr_uint			= contenttypeid
	sql_attr_uint			= groupid
	sql_attr_uint			= primaryid
	sql_attr_timestamp		= dateline
	sql_attr_timestamp		= groupdateline
	sql_attr_uint			= userid
	sql_attr_uint			= groupuserid
	sql_attr_uint			= visible
    sql_attr_bool           = deleted
    sql_attr_bool           = isfirst
    sql_attr_str2ordinal    = grouptitlesort
    sql_attr_str2ordinal    = usernamesort
    sql_attr_str2ordinal    = groupusernamesort
	
	sql_attr_multi = uint tagid from query; SELECT 0, 0
}

source BlogCommentDeltaSource: BlogCommentMainSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

	sql_query 				= \
		SELECT \
			(bt.blogtextid*64 + c.contenttypeid) AS id, \
			c.contenttypeid AS contenttypeid, \
			bt.blogid AS groupid, \
			bt.blogtextid AS primaryid, \
			bt.dateline AS dateline, \
			b.lastcomment AS groupdateline, \
			b.title AS grouptitle, \
			bt.bloguserid AS userid, \
			bt.bloguserid AS groupuserid, \
			bt.pagetext AS keywordtext, \
			IF(bt.state = 'visible', 1, 0) AS visible, \
            0 AS deleted, \
            0 AS isfirst, \
            b.title AS grouptitlesort, \
            bt.username AS usernamesort, \
            bt.username AS groupusernamesort \
		FROM {table_prefix}blog_text AS bt \
		JOIN {table_prefix}blog AS b ON \
			(b.blogid = bt.blogid AND b.firstblogtextid != bt.blogtextid) \
		JOIN {table_prefix}vbsphinxsearch_queue sq ON \
			(sq.primaryid = bt.blogtextid AND  sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'BlogComment')) \
		JOIN {table_prefix}contenttype AS c \
		WHERE \
			c.class = 'BlogComment' \
			AND bt.blogtextid>=$start AND bt.blogtextid<=$end

    sql_query_post_index = SELECT 1;
}

source CMSArticlesMainSource : DBSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

    sql_query_pre = \
        UPDATE \
            {table_prefix}vbsphinxsearch_queue \
        SET \
            `done` = '1' \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'Article')


	sql_query 				= \
		SELECT \
			(a.contentid*64 + c.contenttypeid) AS id, \
			a.contentid AS groupid, \
			c.contenttypeid AS contenttypeid, \
			a.contentid AS primaryid, \
			n.publishdate AS dateline, \
			n.publishdate AS groupdateline, \
			i.title AS grouptitle, \
			n.userid AS userid, \
	        0 AS deleted, \
			a.pagetext AS keywordtext, \
			1 AS visible, \
            1 AS isfirst, \
            i.title AS grouptitlesort, \
            u.username AS usernamesort, \
            u.username AS groupusernamesort \
		FROM {table_prefix}cms_article a \
		LEFT JOIN {table_prefix}cms_node n ON \
			n.contentid = a.contentid \
  		LEFT JOIN {table_prefix}cms_nodeinfo i ON \
			i.nodeid = n.nodeid \
  		LEFT JOIN {table_prefix}user u ON \
			u.userid = n.userid \
  		JOIN {table_prefix}contenttype AS c \
		WHERE \
			n.contenttypeid = c.contenttypeid \
			AND c.class = 'Article' \
			AND a.contentid>=$start AND a.contentid<=$end
		
	sql_query_range		= SELECT MIN(contentid),MAX(contentid) FROM {table_prefix}cms_article
		
	sql_range_step      = 1024

    sql_query_post_index = \
        DELETE FROM \
            {table_prefix}vbsphinxsearch_queue \
        WHERE \
            contenttypeid = \
                (SELECT contenttypeid \
                    FROM {table_prefix}contenttype \
                    WHERE class = 'Article') AND \
            done=1;

    sql_query_post_index = \
        REPLACE INTO \
            {table_prefix}vbsphinxsearch_counters ( contenttypeid, maxprimaryid ) \
        SELECT \
            contenttypeid, (($maxid -  contenttypeid)/64) \
        FROM \
            {table_prefix}contenttype \
        WHERE \
            class = 'Article'

    sql_query_killlist = \
        SELECT \
            ((sq.primaryid )*64 + sq.contenttypeid) AS id \
        FROM \
            {table_prefix}vbsphinxsearch_queue AS sq \
        LEFT JOIN {table_prefix}vbsphinxsearch_counters AS sc ON \
			sq.contenttypeid = sc.contenttypeid \
        WHERE \
            sq.contenttypeid = (SELECT contenttypeid FROM {table_prefix}contenttype WHERE class = 'Article') AND \
            sq.primaryid <= sc.maxprimaryidDis
	
	sql_attr_uint			= contenttypeid
	sql_attr_uint			= groupid
	sql_attr_uint			= primaryid
	sql_attr_timestamp		= dateline
	sql_attr_timestamp		= groupdateline
	sql_attr_uint			= userid
	sql_attr_uint			= visible
    sql_attr_bool           = deleted
    sql_attr_bool           = isfirst
    sql_attr_str2ordinal    = grouptitlesort
    sql_attr_str2ordinal    = usernamesort
    sql_attr_str2ordinal    = groupusernamesort
	
	sql_attr_multi = uint tagid from query; SELECT 0, 0
	
}

source CMSArticlesDeltaSource: CMSArticlesMainSource
{
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_pre = SET NAMES UTF8

	sql_query 				= \
		SELECT \
			(a.contentid*64 + c.contenttypeid) AS id, \
			a.contentid AS groupid, \
			c.contenttypeid AS contenttypeid, \
			a.contentid AS primaryid, \
			n.publishdate AS dateline, \
			n.publishdate AS groupdateline, \
			i.title AS grouptitle, \
			n.userid AS userid, \
	        0 AS deleted, \
			a.pagetext AS keywordtext, \
			1 AS visible, \
            1 AS isfirst, \
            i.title AS grouptitlesort, \
            u.username AS usernamesort, \
            u.username AS groupusernamesort \
		FROM \
			{table_prefix}cms_article a \
		LEFT JOIN {table_prefix}cms_node n ON \
			n.contentid = a.contentid \
  		LEFT JOIN {table_prefix}cms_nodeinfo i ON \
			i.nodeid = n.nodeid \
  		LEFT JOIN {table_prefix}user u ON \
			u.userid = n.userid \
  		JOIN {table_prefix}vbsphinxsearch_queue sq ON \
			(sq.primaryid = a.contentid AND  sq.contenttypeid = (SELECT c.contenttypeid FROM {table_prefix}contenttype c WHERE c.class = 'Article')) \
  		JOIN {table_prefix}contenttype AS c \
		WHERE \
			n.contenttypeid = c.contenttypeid \
			AND c.class = 'Article' \
			AND a.contentid>=$start AND a.contentid<=$end

    sql_query_post_index = SELECT 1;
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
    min_word_len    = 1
    charset_type    = utf-8
    {sphinx_stopwords_file}
    {sphinx_wordforms_file}

    min_stemming_len = 4

#        min_prefix_len  = 3
#        min_infix_len   = 0
#        prefix_fields   = grouptitle
#        enable_star     = 1

    charset_table	= 0..9, A..Z->a..z, _, a..z, \
        U+451->U+435, U+401->U+435, U+410..U+42F->U+430..U+44F, U+430..U+44F

    ignore_chars = U+AD, -
    phrase_boundary = ., ?, !, U+2026
	phrase_boundary_step = 100
	
	preopen = 1
	inplace_enable = 1
	inplace_hit_gap = 1M
	inplace_docinfo_gap = 1M

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
    listen = {sphinx_api}

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



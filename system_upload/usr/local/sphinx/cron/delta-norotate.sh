#!/bin/sh
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php ForumDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php ThreadPostDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php DiscussionMessageDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php SocialGroupDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php VisitorMessageDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php BlogEntryDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php BlogCommentDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php CMSArticlesDelta

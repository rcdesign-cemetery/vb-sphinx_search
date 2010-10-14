#!/bin/sh
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php ForumDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php ThreadPostDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php DiscussionMessageDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php SocialGroupDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php VisitorMessageDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php BlogEntryDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php BlogCommentDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php CMSArticlesDelta --rotate

#!/bin/sh
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php ForumDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php ThreadPostDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php DiscussionMessageDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php SocialGroupDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php VisitorMessageDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php BlogEntryDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php BlogCommentDelta --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php CMSArticlesDelta --rotate

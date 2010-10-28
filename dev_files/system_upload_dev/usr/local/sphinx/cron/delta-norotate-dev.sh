#!/bin/sh
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php ForumDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php ThreadPostDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php DiscussionMessageDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php SocialGroupDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php VisitorMessageDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php BlogEntryDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php BlogCommentDelta
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php CMSArticlesDelta

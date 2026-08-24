-- Migration: 20260818_163500_feed_events_video
-- Description: Allow 'video' in feed_events.entity_type so the fifth entity can appear in the feed

-- The ENUM was defined in 20260513_120000_friends_and_feed.sql with only four entities;
-- videos landed afterwards, so their feed events were rejected silently by FeedEventService.
-- MODIFY COLUMN is naturally idempotent: re-running leaves the column in the same state.
ALTER TABLE feed_events
  MODIFY COLUMN entity_type ENUM('book','movie','game','album','video') NULL;

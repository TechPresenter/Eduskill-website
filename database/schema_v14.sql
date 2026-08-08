-- =============================================================================
--  schema_v14.sql — frontend audit fixes (2026-07)
--  Navigation: expose the orphaned public Courses catalogue and the new public
--  Projects page (header "Programs" submenu + footer quick links).
--  Idempotent: fixed ids + INSERT IGNORE. Run AFTER schema_v13.sql.
-- =============================================================================

INSERT IGNORE INTO menus (id, parent_id, title, url, icon, location, target, sort_order, status)
VALUES
  (25, 3,    'Our Projects', 'projects', NULL, 'header', '_self', 6, 1),
  (26, 3,    'Free Courses', 'courses',  NULL, 'header', '_self', 7, 1),
  (56, NULL, 'Free Courses', 'courses',  NULL, 'footer', '_self', 7, 1);

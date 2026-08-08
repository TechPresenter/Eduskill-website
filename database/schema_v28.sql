-- =============================================================================
--  schema_v28.sql — FAQ section appearance settings
--
--  Drives includes/faq-section.php. Every option is a data-attribute the
--  stylesheet keys off, so switching a theme or animation is a settings change
--  with no code edit and no rebuild.
--
--  Idempotent. Run AFTER schema_v27.sql.
-- =============================================================================
USE `pwf`;

INSERT INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`) VALUES
    -- Palette + surface
    ('faq', 'faq_theme',        'ngo',        'text',   'Colour theme'),
    ('faq', 'faq_background',   'mesh',       'text',   'Animated background'),
    ('faq', 'faq_border',       'gradient',   'text',   'Border effect'),

    -- Motion
    ('faq', 'faq_animation',    'slide',      'text',   'Accordion animation'),
    ('faq', 'faq_hover',        'lift',       'text',   'Hover effect'),
    ('faq', 'faq_icon',         'plus-minus', 'text',   'Icon animation'),
    ('faq', 'faq_duration',     '380',        'number', 'Animation duration (ms)'),

    -- Shape + depth
    ('faq', 'faq_radius',       '20',         'number', 'Corner radius (px)'),
    ('faq', 'faq_shadow',       '3',          'number', 'Shadow intensity (0-5)'),
    ('faq', 'faq_glow',         '2',          'number', 'Glow intensity (0-5)'),
    ('faq', 'faq_spacing',      '12',         'number', 'Gap between items (px)'),
    ('faq', 'faq_font_size',    '16',         'number', 'Question font size (px)'),

    -- Behaviour
    ('faq', 'faq_single_open',  '1',          'boolean','Only one answer open at a time'),
    ('faq', 'faq_show_search',  '1',          'boolean','Show the search box'),

    -- Escape hatches
    ('faq', 'faq_custom_css',   '',           'textarea', 'Custom CSS'),
    ('faq', 'faq_custom_js',    '',           'textarea', 'Custom JavaScript')
ON DUPLICATE KEY UPDATE `group_name` = VALUES(`group_name`), `label` = VALUES(`label`);
-- Only group/label refresh on re-run, so an admin's chosen look is never reset.

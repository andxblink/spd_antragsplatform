<?php

/**
 * Strings this layout adds to the interface. English is the canonical base;
 * MessageSource merges the language of the consultation over it, and admins
 * can still override every entry under "Edit texts".
 */

return [
    'status_heading'        => [
        'text'        => 'Current status',
        'description' => 'Heading of the status summary shown above a motion and inside the amendment data table.',
    ],
    'status_label'          => [
        'text'        => 'Status:',
        'description' => 'Label in front of the configured status name, in the summary and in the motion list.',
    ],
    'status_date_decided'   => [
        'text'        => 'Decided on %DATE%',
        'description' => 'Shown when a resolution date exists. Supported placeholders: %DATE%',
    ],
    'status_date_submitted' => [
        'text'        => 'Submitted on %DATE%',
        'description' => 'Shown when a submission date exists. Supported placeholders: %DATE%',
    ],
    'status_date_saved'     => [
        'text'        => 'Saved on %DATE%',
        'description' => 'Shown for drafts that were never submitted. Supported placeholders: %DATE%',
    ],
    'status_draft_submit'   => 'Review and submit draft',
    'select_row'            => [
        'text'        => 'Select: %TITLE%',
        'description' => 'Accessible name of a selection checkbox in the admin motion list. Supported placeholders: %TITLE%',
    ],
    'select_row_untitled'   => [
        'text'        => 'Select motion',
        'description' => 'Accessible name of a selection checkbox in the admin motion list when the row has no title.',
    ],
    'feed_open_new_tab'     => 'Open feed in a new tab',
    'feed_copy_address'     => 'Copy feed address',
];

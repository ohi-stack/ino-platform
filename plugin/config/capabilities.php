<?php
/**
 * INO Platform capability registry.
 *
 * Status values: operational, in_development, planned, experimental.
 * A feature must not be marked operational unless it is implemented,
 * documented, tested, permission-controlled, and repeatable.
 */

if (!defined('ABSPATH')) { exit; }

return array(
    'platform' => array(
        'name' => 'INO Platform',
        'purpose' => 'Digital operating system for the Indigenous Nation of Onegodia.',
        'version' => '1.3.0-dev',
    ),
    'modules' => array(
        'public_website' => array('status'=>'in_development','label'=>'Public Website'),
        'accounts' => array('status'=>'in_development','label'=>'User Accounts'),
        'membership' => array('status'=>'in_development','label'=>'Membership Portal'),
        'identity' => array('status'=>'in_development','label'=>'Identity & Heritage'),
        'genealogy' => array('status'=>'in_development','label'=>'Genealogy & Family Trees'),
        'social' => array('status'=>'in_development','label'=>'Social Community'),
        'dashboard' => array('status'=>'planned','label'=>'Member Dashboard'),
        'programs' => array('status'=>'planned','label'=>'Programs'),
        'volunteers' => array('status'=>'planned','label'=>'Volunteer Portal'),
        'housing' => array('status'=>'planned','label'=>'Housing Portal'),
        'treasury' => array('status'=>'planned','label'=>'Treasury'),
        'grants' => array('status'=>'planned','label'=>'Grants'),
        'education' => array('status'=>'planned','label'=>'Learning Center'),
        'events' => array('status'=>'planned','label'=>'Events'),
        'documents' => array('status'=>'planned','label'=>'Document Center'),
        'certificates' => array('status'=>'planned','label'=>'Certificate System'),
        'communications' => array('status'=>'planned','label'=>'Communications'),
        'marketplace' => array('status'=>'planned','label'=>'Marketplace'),
        'media' => array('status'=>'planned','label'=>'Media Center'),
        'maps' => array('status'=>'planned','label'=>'Interactive Maps'),
        'mobile' => array('status'=>'planned','label'=>'Mobile & PWA'),
        'admin' => array('status'=>'in_development','label'=>'Administrative Portal'),
        'integrations' => array('status'=>'planned','label'=>'Integrations'),
        'security' => array('status'=>'in_development','label'=>'Security & Compliance'),
        'reporting' => array('status'=>'planned','label'=>'Reporting & Analytics'),
    ),
);

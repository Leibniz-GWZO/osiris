<?php

/**
 * Add-on "Processes": section of the OSIRIS mail digest (routes/cron.php
 * loads the digest.php of every add-on, without a session). See Processes::digest().
 */

require_once __DIR__ . '/php/Conditions.php';
require_once __DIR__ . '/php/Form.php';
require_once __DIR__ . '/php/Files.php';
require_once __DIR__ . '/php/Notify.php';
require_once __DIR__ . '/php/Processes.php';

$GLOBALS['OSIRIS_ADDON_HOOKS']['digest'][] = ['Processes', 'digest'];

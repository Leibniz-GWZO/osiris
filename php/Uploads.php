<?php

/**
 * Permissions for uploaded documents (collection `uploads`).
 *
 * Documents are stored as /uploads/<id>.<extension> and are delivered by the
 * route /uploads/..., which checks these permissions. Only public images
 * (see PUBLIC_DIRS) are delivered directly by the web server.
 *
 * This file is part of the OSIRIS package.
 * Copyright (c) 2026 Julia Koblitz, OSIRIS Solutions GmbH
 *
 * @package OSIRIS
 * @license MIT
 */

require_once "DB.php";
require_once "Project.php";

class Uploads
{
    // directories below /uploads with public images (portfolio, website), see .htaccess
    public const PUBLIC_DIRS = ['topics', 'projects', 'groups'];

    /**
     * Can the current user see or download this document?
     *
     * @param array|object $doc document from `uploads` (needs type and id)
     */
    public static function canView($doc, $Settings)
    {
        if (empty($_SESSION['username'])) return false;
        switch ($doc['type'] ?? '') {
            case 'activities':
                // activities and their documents are visible to all logged-in users
                return true;
            case 'nagoya-permit':
                if ($Settings->hasPermission('nagoya.view')) return true;
                return self::canViewProposalDocuments($doc['id'] ?? null, $Settings);
            case 'proposals':
                return self::canViewProposalDocuments($doc['id'] ?? null, $Settings);
            default:
                return $Settings->hasPermission('documents') || self::isUploader($doc);
        }
    }

    /**
     * Can the current user upload documents to this element, or edit and delete its documents?
     *
     * @param array|object $doc document from `uploads`, or at least type and id of the element
     */
    public static function canEdit($doc, $Settings)
    {
        if (empty($_SESSION['username'])) return false;
        $type = $doc['type'] ?? '';
        $id = $doc['id'] ?? null;
        switch ($type) {
            case 'activities':
                $DB = new DB;
                $activity = $DB->db->activities->findOne(['_id' => DB::to_ObjectID($id)]);
                if (empty($activity)) return false;
                return $DB->isUserActivity(DB::doc2Arr($activity), $_SESSION['username']) || $Settings->hasPermission('activities.edit');
            case 'nagoya-permit':
                if ($Settings->hasPermission('nagoya.view')) return true;
                $proposal = self::getProposal($id);
                return !empty($proposal) && Project::getProposalPermissions($proposal, $Settings)['edit'];
            case 'proposals':
                $proposal = self::getProposal($id);
                if (empty($proposal)) return false;
                $perm = Project::getProposalPermissions($proposal, $Settings);
                if (Project::isRestrictedType($proposal['type'] ?? '')) {
                    return $perm['review'] || $perm['own'];
                }
                return $perm['view'] && ($perm['edit'] || $Settings->hasPermission('proposals.upload-documents'));
            default:
                return $Settings->hasPermission('admin.see');
        }
    }

    /**
     * Uploaders can change their own documents, as long as they can still see them.
     */
    public static function canChange($doc, $Settings)
    {
        return self::canEdit($doc, $Settings) || (self::isUploader($doc) && self::canView($doc, $Settings));
    }

    public static function canViewProposalDocuments($id, $Settings)
    {
        $proposal = self::getProposal($id);
        if (empty($proposal)) return false;
        $perm = Project::getProposalPermissions($proposal, $Settings);
        if (!$perm['view']) return false;
        if (Project::isRestrictedType($proposal['type'] ?? '')) {
            return $perm['review'] || $perm['own'];
        }
        return $perm['own'] || $Settings->hasPermission('proposals.view-documents');
    }

    private static function isUploader($doc)
    {
        return !empty($doc['uploaded_by']) && $doc['uploaded_by'] == ($_SESSION['username'] ?? null);
    }

    private static function getProposal($id)
    {
        if (empty($id) || !DB::is_ObjectID($id)) return null;
        $DB = new DB;
        return $DB->db->proposals->findOne(['_id' => DB::to_ObjectID($id)]);
    }
}

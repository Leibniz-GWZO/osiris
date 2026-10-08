<?php

/**
 * Attachments of cases, stored in the MongoDB GridFS bucket proc_files.
 *
 * Not stored below /uploads on purpose: the core delivers files there to
 * all logged-in users. GridFS files are part of the nightly mongodump.
 */
class ProcessFiles
{
    public const MAX_SIZE = 20 * 1024 * 1024;
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp', 'png', 'jpg', 'jpeg', 'gif', 'msg', 'eml', 'zip'];

    private static function bucket(Processes $P)
    {
        return $P->db->selectGridFSBucket(['bucketName' => 'proc_files']);
    }

    /** Returns an error message or null. */
    public static function upload(Processes $P, $case, $file)
    {
        if (empty($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return lang('The file could not be uploaded.', 'Die Datei konnte nicht hochgeladen werden.');
        }
        if ($file['size'] > self::MAX_SIZE) {
            return lang('The file is larger than 20 MB.', 'Die Datei ist größer als 20 MB.');
        }
        $name = basename(str_replace('\\', '/', (string) $file['name']));
        $name = preg_replace('/[\x00-\x1F\x7F"]/u', '', $name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS)) {
            return lang('This file type is not allowed: ', 'Dieser Dateityp ist nicht erlaubt: ') . e($ext);
        }
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $stream = fopen($file['tmp_name'], 'rb');
        $fileId = self::bucket($P)->uploadFromStream($name, $stream, [
            'metadata' => ['case_id' => strval($case['_id']), 'mime' => $mime, 'by' => $P->user],
        ]);
        fclose($stream);
        $now = date('Y-m-d H:i:s');
        $entry = ['id' => strval($fileId), 'name' => $name, 'size' => intval($file['size']), 'mime' => $mime, 'by' => $P->user, 'at' => $now];
        $P->db->proc_cases->updateOne(['_id' => DB::to_ObjectID($case['_id'])], [
            '$push' => [
                'files' => $entry,
                'history' => ['at' => $now, 'by' => $P->user, 'action' => 'file_added', 'comment' => $name],
            ],
            '$set' => ['updated_at' => $now],
        ]);
        return null;
    }

    public static function find($case, $fileId)
    {
        foreach (DB::doc2Arr($case['files'] ?? []) as $f) {
            if (($f['id'] ?? null) === $fileId) return DB::doc2Arr($f);
        }
        return null;
    }

    public static function canDelete(Processes $P, $case, $file): bool
    {
        if (!$P->isOpen($case)) return false;
        return ($file['by'] ?? null) == $P->user;
    }

    public static function download(Processes $P, $case, $fileId)
    {
        $file = self::find($case, $fileId);
        if (empty($file) || !DB::is_ObjectID($fileId)) abortwith(404, lang('File', 'Datei'));
        $name = str_replace(['"', "\r", "\n"], '', $file['name']);
        header('Content-Type: ' . ($file['mime'] ?? 'application/octet-stream'));
        header('Content-Length: ' . intval($file['size']));
        header('Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'wb');
        self::bucket($P)->downloadToStream(DB::to_ObjectID($fileId), $out);
        fclose($out);
        die;
    }

    public static function delete(Processes $P, $case, $fileId)
    {
        $file = self::find($case, $fileId);
        if (empty($file)) return lang('File not found.', 'Datei nicht gefunden.');
        if (!self::canDelete($P, $case, $file)) return lang('You cannot delete this file.', 'Du kannst diese Datei nicht löschen.');
        try {
            self::bucket($P)->delete(DB::to_ObjectID($fileId));
        } catch (Exception $e) {
            // file is already gone from the bucket
        }
        $now = date('Y-m-d H:i:s');
        $P->db->proc_cases->updateOne(['_id' => DB::to_ObjectID($case['_id'])], [
            '$pull' => ['files' => ['id' => $fileId]],
            '$push' => ['history' => ['at' => $now, 'by' => $P->user, 'action' => 'file_deleted', 'comment' => $file['name']]],
            '$set' => ['updated_at' => $now],
        ]);
        return null;
    }

    public static function deleteAll(Processes $P, $case)
    {
        foreach (DB::doc2Arr($case['files'] ?? []) as $f) {
            try {
                self::bucket($P)->delete(DB::to_ObjectID($f['id']));
            } catch (Exception $e) {
            }
        }
    }
}

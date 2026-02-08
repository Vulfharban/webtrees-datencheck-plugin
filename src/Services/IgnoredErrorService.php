<?php

namespace Wolfrum\Datencheck\Services;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Tree;
use Wolfrum\Datencheck\Services\SchemaService;

class IgnoredErrorService
{
    /**
     * Ignore an error for a specific person
     */
    public static function ignoreError(Tree $tree, string $xref, string $errorCode, string $comment = ''): bool
    {
        try {
            // Ensure table exists before writing
            SchemaService::updateSchema();

            DB::table('datencheck_ignored')->updateOrInsert([
                'tree_id'    => $tree->id(),
                'xref'       => $xref,
                'error_code' => $errorCode,
            ], [
                'user_name'  => Auth::user()->userName(),
                'comment'    => $comment,
                'created_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if an error is ignored
     */
    public static function isIgnored(int $treeId, string $xref, string $errorCode): bool
    {
        try {
            return DB::table('datencheck_ignored')
                ->where('tree_id', $treeId)
                ->where('xref', $xref)
                ->where('error_code', $errorCode)
                ->exists();
        } catch (\Throwable $e) {
            // Table likely missing -> not ignored
            return false;
        }
    }

    /**
     * Get all ignored errors for a person (to filter them out efficiently)
     * Returns array of error codes.
     */
    public static function getIgnoredCodesForPerson(int $treeId, string $xref): array
    {
        try {
            return DB::table('datencheck_ignored')
                ->where('tree_id', $treeId)
                ->where('xref', $xref)
                ->pluck('error_code')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Remove an ignored error (Un-Ignore)
     */
    public static function unignoreError(Tree $tree, string $xref, string $errorCode): bool
    {
        try {
            return DB::table('datencheck_ignored')
                ->where('tree_id', $tree->id())
                ->where('xref', $xref)
                ->where('error_code', $errorCode)
                ->delete() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get all ignored errors for a tree (for admin view)
     * 
     * @return array<object>
     */
    public static function getIgnoredErrors(Tree $tree): array
    {
        try {
            return DB::table('datencheck_ignored')
                ->where('tree_id', $tree->id())
                ->orderBy('created_at', 'desc')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}

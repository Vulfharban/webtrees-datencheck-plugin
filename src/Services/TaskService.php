<?php

namespace Wolfrum\Datencheck\Services;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Individual;

class TaskService
{
    /**
     * Add a Research Task (_TODO) to an individual.
     *
     * @param Tree   $tree
     * @param string $xref
     * @param string $title
     * @param string $note
     * @param string $user
     *
     * @return array<string,string|bool>
     */
    public static function addTask(Tree $tree, string $xref, string $title, string $note = '', string $user = ''): array
    {
        $individual = Registry::individualFactory()->make($xref, $tree);

        if (!$individual instanceof Individual) {
            return ['success' => false, 'message' => 'Person not found'];
        }

        if (!$individual->canEdit()) {
            return ['success' => false, 'message' => 'Permission denied'];
        }

        // basic cleanup
        $title = strip_tags(trim($title));
        $note  = strip_tags(trim($note));

        // Build GEDCOM string
        // Level 1 _TODO <Title>
        $gedcom = '1 _TODO ' . $title;

        // Level 2 DATE
        $gedcom .= "\n2 DATE " . date('d M Y');

        // Level 2 _WT_USER
        if ($user === '') {
            $user = Auth::user()->userName();
        }
        if ($user !== '') {
            $gedcom .= "\n2 _WT_USER " . $user;
        }

        // Level 2 NOTE
        if ($note !== '') {
            // Handle multiline notes with CONT
            $lines = explode("\n", $note);
            $first = true;
            foreach ($lines as $line) {
                if ($first) {
                    $gedcom .= "\n2 NOTE " . $line;
                    $first = false;
                } else {
                    $gedcom .= "\n3 CONT " . $line;
                }
            }
        }

        try {
            // updateFact with empty ID adds a new fact
            $individual->updateFact('', $gedcom, true);
            return ['success' => true, 'message' => 'Task created'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

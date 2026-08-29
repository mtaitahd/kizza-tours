<?php
/**
 * Persists a tour's FAQ list by reconciling with the stored rows.
 *
 * Shares the exact safety model used by saveItineraryDays():
 *  - rows are matched by id; existing rows update, new rows (id 0) insert, and
 *    only rows that existed before AND were absent from the submitted set are
 *    removed — a malformed request can never silently wipe FAQs.
 *  - participates in an already-open outer transaction so the whole tour save
 *    remains atomic.
 *  - only FAQs whose tour_id matches the given tour are ever touched.
 *
 * @param int   $tourId The tour id the FAQs belong to.
 * @param array $faqs   Each item: id, question, answer, category, sort_order,
 *                      status. question/answer are trimmed; empty questions are
 *                      dropped (a FAQ needs at least a question).
 */
function saveTourFaqs($tourId, $faqs) {
    $db = db();
    $tourId = intval($tourId);
    if ($tourId <= 0) return;

    $oldRows = $db->fetchAll("SELECT id FROM faq WHERE tour_id = ?", [$tourId]);
    $oldIds = [];
    foreach ($oldRows as $row) {
        $oldIds[intval($row['id'])] = true;
    }

    $submitted = [];
    $submittedIds = [];
    $sort = 0;
    foreach (is_array($faqs) ? $faqs : [] as $f) {
        $question = trim($f['question'] ?? '');
        if ($question === '') continue;
        $id = intval($f['id'] ?? 0);
        $submitted[$sort] = [
            'id'         => $id,
            'question'   => mb_substr($question, 0, 190),
            'answer'     => trim($f['answer'] ?? ''),
            'category'   => mb_substr(trim($f['category'] ?? ''), 0, 100),
            'sort_order' => intval($f['sort_order'] ?? $sort),
            'status'     => in_array(trim($f['status'] ?? ''), ['active', 'inactive'], true) ? trim($f['status']) : 'active',
        ];
        if ($id > 0 && isset($oldIds[$id])) $submittedIds[$id] = true;
        $sort++;
    }

    $outerTxn = $db->getConnection()->inTransaction();

    try {
        if (!$outerTxn) $db->beginTransaction();
        foreach ($submitted as $row) {
            if ($row['id'] > 0 && isset($oldIds[$row['id']])) {
                $db->query(
                    "UPDATE faq SET question=?, answer=?, category=?, sort_order=?, status=? WHERE id=? AND tour_id=?",
                    [$row['question'], $row['answer'], $row['category'], $row['sort_order'], $row['status'], $row['id'], $tourId]
                );
            } else {
                $db->insert(
                    "INSERT INTO faq (tour_id, question, answer, category, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)",
                    [$tourId, $row['question'], $row['answer'], $row['category'], $row['sort_order'], $row['status']]
                );
            }
        }
        foreach ($oldIds as $id => $_unused) {
            if (isset($submittedIds[$id])) continue;
            $db->query("DELETE FROM faq WHERE id=? AND tour_id=?", [$id, $tourId]);
        }
        if (!$outerTxn) $db->commit();
    } catch (\Throwable $e) {
        if (!$outerTxn && $db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        error_log("saveTourFaqs error: " . $e->getMessage());
        throw $e;
    }
}
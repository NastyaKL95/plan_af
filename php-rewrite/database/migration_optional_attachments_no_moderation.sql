-- Убрана модерация доп. файлов этапа: снять блокировки оценивания (pending/rejected).
UPDATE submissions s
SET
    optional_attachments_status = CASE
        WHEN EXISTS (SELECT 1 FROM submission_attachments sa WHERE sa.submission_id = s.id) THEN 'approved'
        ELSE 'na'
    END,
    optional_attachments_rejection_reason = '',
    optional_attachments_reviewed_at = NULL,
    optional_attachments_reviewed_by = NULL
WHERE s.optional_attachments_status IN ('pending', 'rejected');

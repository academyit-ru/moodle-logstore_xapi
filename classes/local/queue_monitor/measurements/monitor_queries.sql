SELECT 'total' status, COUNT(*)
FROM edu2_mdl_logstore_xapi_queue q
WHERE isbanned = 0
;
SELECT 'total queue:EMIT_STATEMENTS' status, COUNT(*)
FROM edu2_mdl_logstore_xapi_queue q
WHERE
    q.queue LIKE 'EMIT_STATEMENTS'
    AND q.isbanned = 0
;
SELECT 'total queue:PUBLISH_ATTACHMENTS' status, COUNT(*)
FROM edu2_mdl_logstore_xapi_queue q
WHERE
    q.queue LIKE 'PUBLISH_ATTACHMENTS'
    AND q.isbanned = 0
;
SELECT 'isrunning' status, COUNT(*)
FROM edu2_mdl_logstore_xapi_queue q
WHERE
    q.isrunning = 1
    AND q.isbanned = 0
;
SELECT 'stuck running' status, COUNT(*)
FROM edu2_mdl_logstore_xapi_queue q
WHERE
    q.isrunning = 1
    AND q.timecompleted = 0
    AND (NOW() - TO_TIMESTAMP(q.timestarted) > INTERVAL '24h')
    AND q.isbanned = 0
;

SELECT 'isbanned' status, COUNT(*)
FROM edu2_mdl_logstore_xapi_queue q
WHERE
    q.isbanned = 1
;

SELECT 'attachments published' metric, COUNT(*)
FROM edu2_mdl_logstore_xapi_attachments
;

SELECT 'LRS records published' metric, COUNT(*)
FROM edu2_mdl_logstore_xapi_records
;

-- UPDATE edu2_mdl_logstore_xapi_queue
-- SET isrunning = 0
-- WHERE
--     isrunning = 1
--     AND (NOW() - TO_TIMESTAMP(q.timestarted) > INTERVAL '24h')

-- UPDATE edu2_mdl_logstore_xapi_queue
-- SET isrunning = 0, timecompleted = timemodified
-- WHERE
--     isrunning = 1
--     AND isbanned = 1
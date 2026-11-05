-- migrate_v12.sql — Problem 19: covering indexes for the hot queue filters
-- Admin dashboard filters applicants by (status, course) together;
-- office queues subquery documents(status, doc_type) and payments(status).

ALTER TABLE applicants
    ADD INDEX idx_status_course (status, course);

ALTER TABLE documents
    ADD INDEX idx_status_doctype (status, doc_type);

ALTER TABLE payments
    ADD INDEX idx_status (status);

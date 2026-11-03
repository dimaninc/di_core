CREATE TABLE IF NOT EXISTS cashdesk_journal
(
    id              bigint       auto_increment,
    journal_id      varchar(36)  not null,
    seq             bigint       not null,
    source          varchar(255) default '',
    receipt_id      bigint       default null,
    state           varchar(255) default '',
    document_number bigint       default null,
    event_at        datetime     default null,
    payload         json         not null,
    received_at     datetime     not null,
    unique key idx_journal_seq (journal_id, seq),
    key idx_receipt (source, receipt_id),
    primary key (id)
)
    DEFAULT CHARSET = 'utf8mb4'
    COLLATE = 'utf8mb4_general_ci'
    ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS feedback
(
    id      bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id bigint,
    name    text        default '',
    email   text        default '',
    phone   text        default '',
    content text,
    ip      cidr,
    date    timestamptz default CURRENT_TIMESTAMP
);

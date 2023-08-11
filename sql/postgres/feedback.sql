CREATE TABLE IF NOT EXISTS feedback
(
    id      SERIAL PRIMARY KEY,
    user_id bigint,
    name    varchar(100) default '',
    email   varchar(60)  default '',
    phone   varchar(20)  default '',
    content text,
    ip      cidr,
    date    timestamp    default CURRENT_TIMESTAMP
);

-- Worker wake-up (LISTEN/NOTIFY). Has a $$ body: run it whole (PDO::exec), not through
-- Migration::executeSqlFile() – its line parser does not understand $$.
--
-- Arguments of the trigger: the channel (Setup::CHANNEL) and, for a table without a queue
-- column, a fixed payload ('mail' on mail_queue – Worker::MAIL):
--   CREATE TRIGGER trg__job__notify AFTER INSERT ON job
--     FOR EACH ROW EXECUTE FUNCTION di_queue_notify('di_queue');
--   CREATE TRIGGER trg__mail_queue__notify AFTER INSERT ON mail_queue
--     FOR EACH ROW EXECUTE FUNCTION di_queue_notify('di_queue', 'mail');
-- IF, not CASE or COALESCE: an expression with NEW.queue fails the insert into a table
-- without that column even in a branch not taken; a statement of an IF branch not taken is
-- never planned.
CREATE OR REPLACE FUNCTION di_queue_notify() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF TG_NARGS > 1 THEN
    PERFORM pg_notify(TG_ARGV[0], TG_ARGV[1]);
  ELSE
    PERFORM pg_notify(TG_ARGV[0], NEW.queue);
  END IF;
  RETURN NULL;
END
$$;

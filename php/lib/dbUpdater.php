<?php
/*
    // dimaninc

    // 2015/01/31
    	* force update option added

    // 2014/11/24
        * .sql support added

    // 2012/02/07
        * some new stuff
*/

/**
 * Class DBUpdater
 * @deprecated
 * Use migrations instead
 */
class DBUpdater
{
    /** @var diDB */
    public $db;
    public $log;

    public function __construct($db)
    {
        $this->db = $db;
        $this->log = [];
    }

    public function update($updates)
    {
        $alreadyDone = $this->loadUpdates();
        //$db = $this->db;  // $db will be visible to updates

        foreach ($updates as $name => $info) {
            $force = substr($info, 0, 3) == '!!!';

            if (!@$alreadyDone[$name] || $force) {
                $this->log[] = "<hr><b>Applying $name: $info</b>";

                // now including all .php files inside the update directory
                $path = "{$_SERVER['DOCUMENT_ROOT']}/_create_tables/$name";
                $handle = opendir($path);

                if ($this->db->getLog()) {
                    $this->log[] = "\$db errors before the update:";
                    $this->log = array_merge($this->log, $this->db->getLog());

                    $this->db->resetLog();
                }

                while ($f = readdir($handle)) {
                    if (is_file($path . '/' . $f)) {
                        switch (strtolower(get_file_ext($f))) {
                            case 'php':
                                $this->log[] = "executing $path/$f";

                                if (empty($db)) {
                                    $db = &$this->db;
                                }

                                require_once $path . '/' . $f;

                                break;

                            case 'sql':
                                $this->log[] = "executing $path/$f";

                                $this->db->mq(
                                    file_get_contents($path . '/' . $f)
                                );

                                break;
                        }
                    }
                }

                closedir($handle);

                if (count($this->db->getLog()) == 0) {
                    $this->updateDone($name, $info);
                    $this->log[] = "<b>Update $name done</b>";
                } else {
                    $this->log[] = "<b>Update $name not done!</b> It will be executed next time";
                    $this->log[] =
                        '<b>DB Errors:</b><br>' .
                        join('<br>', $this->db->getLog());
                }
            } else {
                $this->log[] = "Skipping update $name (already done)";
            }
        }
    }

    private function loadUpdates()
    {
        $updates = [];

        if ($this->db->fetch($this->db->q("SHOW TABLES LIKE 'db_updates'"))) {
            $this->log[] = 'Table db_updates exists';

            $rs = $this->db->rs('db_updates', '', 'name');
            while ($r = $this->db->fetch($rs)) {
                $updates[$r->name] = true;
            }
        } else {
            $this->createUpdatesTable();

            // special check for version 0
            $rs = $this->db->q("SHOW TABLES LIKE 'admins'");
            if ($this->db->fetch($rs)) {
                $this->log[] =
                    'Table admins exists. Initial version of DB already loaded';

                $updates['v00-init'] = true;
                $this->updateDone('v00-init', 'initial structure');
            }
        }

        return $updates;
    }

    private function createUpdatesTable()
    {
        $this->db->q("CREATE TABLE db_updates(
      id int not null auto_increment,
      name varchar(128) not null,
      info varchar(250) not null,
      done_at timestamp not null default '2000-01-01',
      primary key(id)
    )");
    }

    private function updateDone($name, $info)
    {
        $this->db->insert('db_updates', [
            'name' => $name,
            'info' => str_in($info),
            '*done_at' => 'now()',
        ]);
    }

    public function getLog()
    {
        return $this->log;
    }
}

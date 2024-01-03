<?php
/*
    // dimaninc

    // 2010/09/13
        * birthdate
*/

use diCore\Helper\StringHelper;

class diSVNUpdater
{
    private $svn;
    private $ftp;
    private $current_version;

    private $logging;
    private $log_fp;
    private $log_ar;
    private $log_info_ar;
    private $log_idx_ar;

    private $log_fn = 'log/updater-log.xml';

    function diSVNUpdater($logging = true)
    {
        $this->logging = $logging;
        $this->log_ar = [];
        $this->log_info_ar = [
            'total_message' => 7,
            'total_file' => 0,
        ];
        $this->log_idx_ar = [
            'message' => 0,
            'file' => 0,
        ];

        $this->read_current_version();
    }

    function go()
    {
        $this->log('Update process started');

        //$this->connect_to_svn("http://dimaninctest.googlecode.com/svn/");
        $this->connect_to_svn(
            'https://dimaninctest.googlecode.com/svn/',
            'dimaninc',
            'VY2gZ5Mq5Nb2'
        );

        $this->log('Connected to the Update Server');

        $this->connect_to_ftp(
            diConfiguration::get('ftp_host'),
            diConfiguration::get('ftp_login'),
            diConfiguration::get('ftp_password')
        );

        $this->log('Connected to the Client App FTP Server');

        list($dirs_ar, $files_ar, $new_version) = $this->get_contents_from_svn();

        if ($new_version == $this->current_version) {
            $this->log_info_ar['total_message'] = 4;
            $this->log(
                "Update not needed - you are now running the latest (V{$new_version}) of sNOWsh"
            );

            return $new_version;
        }

        $this->log_info_ar['total_file'] = count($files_ar);
        $this->log('Got the list of update files');

        $this->create_new_dirs_on_ftp($dirs_ar);

        $this->log('Uploading files');

        $this->store_files_on_ftp($files_ar);

        $this->log('Files uploaded');

        $this->store_current_version($new_version);

        $this->log(
            "Update complete - you are now running V{$new_version} of sNOWsh"
        );

        return $new_version;
    }

    function log($content, $type = 'message')
    {
        if ($this->logging) {
            $this->log_ar[] = [
                'idx' => ++$this->log_idx_ar[$type],
                'type' => $type,
                'content' => $content,
            ];

            $this->flush_log();
        }
    }

    function get_log_header()
    {
        $xml = '';

        foreach ($this->log_info_ar as $k => $v) {
            $xml .= " $k=\"$v\"";
        }

        return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<log>\n<info$xml />\n";
    }

    function get_log_footer()
    {
        return '</log>';
    }

    function get_log_line($ar)
    {
        return "<{$ar['type']} idx=\"{$ar['idx']}\">" .
            StringHelper::out($ar['content']) .
            "</{$ar['type']}>\n";
    }

    function flush_log()
    {
        $xml = $this->get_log_header();

        foreach ($this->log_ar as $ar) {
            $xml .= $this->get_log_line($ar);
        }

        $xml .= $this->get_log_footer();

        $fp = fopen(diPaths::fileSystem() . $this->log_fn, 'w');
        fputs($fp, $xml, strlen($xml));
        fclose($fp);
    }

    function store_files_on_ftp($files_ar)
    {
        create_folders_chain(diPaths::fileSystem(), get_tmp_folder(), 0775);

        foreach ($files_ar as $f) {
            $tmp_fn = get_unique_id() . '.tmp';

            $fp = fopen(diPaths::fileSystem() . get_tmp_folder() . $tmp_fn, 'w');
            fputs($fp, $f['contents']);
            fclose($fp);

            $this->ftp->simple_put(
                diPaths::fileSystem() . get_tmp_folder() . $tmp_fn,
                $f['path']
            );

            unlink(diPaths::fileSystem() . get_tmp_folder() . $tmp_fn);

            $this->log($f['path'], 'file');
        }
    }

    function create_new_dirs_on_ftp($dirs_ar)
    {
        foreach ($dirs_ar as $d) {
            $this->ftp->make_dir_chain($d['path']);
        }
    }

    function get_contents_from_svn()
    {
        $logs = $this->svn->getRepositoryLogs($this->current_version);

        // extracting new files
        $files_ar = [];
        foreach ($logs as $ar) {
            if (!isset($ar['files'])) {
                continue;
            }

            if (!is_array($ar['files'])) {
                $ar['files'] = [$ar['files']];
            }

            $files_ar = array_merge($files_ar, $ar['files']);

            $new_version = $ar['version'];
        }
        //

        $files_ar = array_unique($files_ar);

        // filtering the files
        $old_files_ar = $files_ar;

        $files_ar = [];
        $dirs_ar = [];

        foreach ($old_files_ar as $f) {
            if ($f && substr($f, 0, 7) == '/trunk/') {
                $dir_info_ar = $this->svn->getDirectoryFiles($f);

                if ($dir_info_ar) {
                    $dirs_ar[] = [
                        'path' => substr($f, 7),
                    ];
                } else {
                    $files_ar[] = [
                        'path' => substr($f, 7),
                        'contents' => $this->svn->getFile($f),
                    ];
                }
            }
        }
        //

        return [$dirs_ar, $files_ar, $new_version];
    }

    function read_current_version()
    {
        global $__CONFIG;

        if (!isset($__CONFIG['__updater_current_version'])) {
            $this->store_current_version(0);
        } else {
            $this->current_version = diConfiguration::get(
                '__updater_current_version'
            );
        }
    }

    function store_current_version($new_version = false)
    {
        global $cfg;

        if ($new_version !== false) {
            $this->current_version = $new_version;
        }

        $cfg->set('__updater_current_version', 'int', $this->current_version);
    }

    function connect_to_svn($url, $login = '', $password = '')
    {
        $this->svn = new phpsvnclient();

        if ($login && $password) {
            $this->svn->setAuth($login, $password);
        }

        $this->svn->setRepository($url);
    }

    function connect_to_ftp($host, $login, $password)
    {
        $this->ftp = new diFTP($host, $login, $password);
    }
}

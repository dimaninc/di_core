<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.02.20
 * Time: 13:44
 */

namespace diCore\Traits\Admin;

trait DumpActions
{
    protected $file;
    protected $folder;

    public function deleteAction()
    {
        $ar = [
            'file' => $this->file,
            'ok' => false,
        ];

        if ($this->file) {
            $fn = $this->folder . $this->file;

            if (is_file($fn)) {
                unlink($fn);

                $ar['ok'] = true;
            }
        }

        return $ar;
    }

    public function downloadAction()
    {
        $headers = \diRequest::get('headers', 1);

        if ($headers) {
            header('Content-Type: application/download');
            header("Content-Disposition: attachment; filename=\"$this->file\"");
            header('Content-Length: ' . filesize($this->folder . $this->file));
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        readfile($this->folder . $this->file);
    }
}

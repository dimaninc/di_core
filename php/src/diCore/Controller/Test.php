<?php

namespace diCore\Controller;

use diCore\Database\Connection;

class Test extends \diBaseController
{
    public function __construct($params = [])
    {
        die();

        parent::__construct($params);
    }

    public function dbAction()
    {
        $db = Connection::get()->getDb();

        $data = [
            'col1' => 1,
            'col2' => '2',
            'col3' => [1, 2, 3, 'string', '4'],
            'col4' => [
                'a' => 1,
                'b' => '2',
                'c' => [
                    'c1' => 1,
                    'c2' => 2,
                ],
                'd' => 'Multiline
text',
                'e' => 'DROP "zhopa"\'\';{}!@#$%^&*()`±',
            ],
            '*col5' => 'CURRENT_TIMESTAMP',
            'col6' => 'DROP "zhopa"\'\';{}!@#$%^&*()`±',
        ];

        $insert1 = $db->getFullQueryForInsert('alias.table', $data);

        $update1 = $db->getFullQueryForUpdate(
            'alias.table',
            extend($data, ['*col100' => 'col6+1']),
            1
        );

        return "<pre>insert1\n$insert1<br />\n\nupdate1\n$update1</pre>";
    }
}

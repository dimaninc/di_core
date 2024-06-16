<?php

namespace diCore\Controller;

use diCore\Database\Connection;

class Test extends \diBaseController
{
    public function __construct($params = [])
    {
        die();

        // parent::__construct($params);
    }

    public function dbAction()
    {
        $db = Connection::get()->getDb();

        $insert1 = $db->getFullQueryForInsert('alias.table', [
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
            ],
            '*col5' => 'CURRENT_TIMESTAMP',
            'col6' => 'DROP "zhopa"\'\';{}!@#$%^&*()`±',
        ]);

        $update1 = $db->getFullQueryForUpdate(
            'alias.table',
            [
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
                ],
                '*col5' => 'CURRENT_TIMESTAMP',
                'col6' => 'DROP "zhopa"\'\';{}!@#$%^&*()`±',
                '*col100' => 'col6+1',
            ],
            1
        );

        return [
            'insert1' => $insert1,
            'update1' => $update1,
        ];
    }
}

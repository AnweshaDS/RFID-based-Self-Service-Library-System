<?php

return [
    'Admin' => [
        'menu' => [
            ['label' => 'Circulation', 'path' => '/cgi-bin/koha/circ/circulation.pl'],
            ['label' => 'Patrons', 'path' => '/cgi-bin/koha/members/members-home.pl'],
            ['label' => 'Cataloging', 'path' => '/cgi-bin/koha/cataloguing/addbooks.pl'],
            ['label' => 'Serials', 'path' => '/cgi-bin/koha/serials/serials-home.pl'],
            ['label' => 'Acquisitions', 'path' => '/cgi-bin/koha/acqui/acqui-home.pl'],
            ['label' => 'Reports', 'path' => '/cgi-bin/koha/reports/reports-home.pl'],
            ['label' => 'Lists', 'path' => '/cgi-bin/koha/virtualshelves/shelves.pl'],
            ['label' => 'Tools', 'path' => '/cgi-bin/koha/tools/tools-home.pl'],
            ['label' => 'Administration', 'path' => '/cgi-bin/koha/admin/admin-home.pl'],
            ['label' => 'Advanced Search', 'path' => '/cgi-bin/koha/catalogue/search.pl'],
            ['label' => 'Item Search', 'path' => '/cgi-bin/koha/catalogue/itemsearch.pl'],
            ['label' => 'Course Reserves', 'path' => '/cgi-bin/koha/course_reserves/course-reserves.pl'],
            ['label' => 'Authorities', 'path' => '/cgi-bin/koha/authorities/authorities-home.pl'],
        ],
    ],
    'Librarian' => [
        'menu' => [
            ['label' => 'Circulation', 'path' => '/cgi-bin/koha/circ/circulation.pl'],
            ['label' => 'Patrons', 'path' => '/cgi-bin/koha/members/members-home.pl'],
            ['label' => 'Advanced Search', 'path' => '/cgi-bin/koha/catalogue/search.pl'],
            ['label' => 'Item Search', 'path' => '/cgi-bin/koha/catalogue/itemsearch.pl'],
            ['label' => 'Course Reserves', 'path' => '/cgi-bin/koha/course_reserves/course-reserves.pl'],
            ['label' => 'Authorities', 'path' => '/cgi-bin/koha/authorities/authorities-home.pl'],
        ],
    ],
    'Assistant Librarian' => [
        'menu' => [
            ['label' => 'Circulation', 'path' => '/cgi-bin/koha/circ/circulation.pl'],
            ['label' => 'Advanced Search', 'path' => '/cgi-bin/koha/catalogue/search.pl'],
            ['label' => 'Item Search', 'path' => '/cgi-bin/koha/catalogue/itemsearch.pl'],
            ['label' => 'Course Reserves', 'path' => '/cgi-bin/koha/course_reserves/course-reserves.pl'],
            ['label' => 'Authorities', 'path' => '/cgi-bin/koha/authorities/authorities-home.pl'],
        ],
    ],
    'Sub-Controller' => [
        'menu' => [
            ['label' => 'Patrons', 'path' => '/cgi-bin/koha/members/members-home.pl'],
            ['label' => 'Advanced Search', 'path' => '/cgi-bin/koha/catalogue/search.pl'],
            ['label' => 'Item Search', 'path' => '/cgi-bin/koha/catalogue/itemsearch.pl'],
            ['label' => 'Course Reserves', 'path' => '/cgi-bin/koha/course_reserves/course-reserves.pl'],
            ['label' => 'Authorities', 'path' => '/cgi-bin/koha/authorities/authorities-home.pl'],
        ],
    ],
    'Senior Cataloger' => [
        'menu' => [
            ['label' => 'Cataloging', 'path' => '/cgi-bin/koha/cataloguing/addbooks.pl'],
            ['label' => 'Advanced Search', 'path' => '/cgi-bin/koha/catalogue/search.pl'],
            ['label' => 'Item Search', 'path' => '/cgi-bin/koha/catalogue/itemsearch.pl'],
            ['label' => 'Course Reserves', 'path' => '/cgi-bin/koha/course_reserves/course-reserves.pl'],
            ['label' => 'Authorities', 'path' => '/cgi-bin/koha/authorities/authorities-home.pl'],
        ],
    ],
    'Cataloger' => [
        'menu' => [
            ['label' => 'Cataloging', 'path' => '/cgi-bin/koha/cataloguing/addbooks.pl'],
            ['label' => 'Advanced Search', 'path' => '/cgi-bin/koha/catalogue/search.pl'],
            ['label' => 'Item Search', 'path' => '/cgi-bin/koha/catalogue/itemsearch.pl'],
            ['label' => 'Course Reserves', 'path' => '/cgi-bin/koha/course_reserves/course-reserves.pl'],
            ['label' => 'Authorities', 'path' => '/cgi-bin/koha/authorities/authorities-home.pl'],
        ],
    ],
    'Attendant' => [
        'menu' => [
            ['label' => 'Advanced Search', 'path' => '/cgi-bin/koha/catalogue/search.pl'],
            ['label' => 'Item Search', 'path' => '/cgi-bin/koha/catalogue/itemsearch.pl'],
            ['label' => 'Course Reserves', 'path' => '/cgi-bin/koha/course_reserves/course-reserves.pl'],
            ['label' => 'Authorities', 'path' => '/cgi-bin/koha/authorities/authorities-home.pl'],
        ],
    ],
    'Circulation Staff' => [
        'menu' => [
            ['label' => 'Circulation', 'path' => '/cgi-bin/koha/circ/circulation.pl'],
            ['label' => 'Advanced Search', 'path' => '/cgi-bin/koha/catalogue/search.pl'],
            ['label' => 'Item Search', 'path' => '/cgi-bin/koha/catalogue/itemsearch.pl'],
            ['label' => 'Course Reserves', 'path' => '/cgi-bin/koha/course_reserves/course-reserves.pl'],
            ['label' => 'Authorities', 'path' => '/cgi-bin/koha/authorities/authorities-home.pl'],
        ],
    ],
];
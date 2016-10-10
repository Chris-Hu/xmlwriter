# xmlwriter

A simple array to xml Class
It gets rid of the numeric index problem
handles attributes, and helps build huge file,
writing node progressively on disk

**Usage**
``` php

<?php
$xmW = new ExmlWriter("test.xml","root","utf-8",true,true);
$xmW->formatOutput = true;
$city = ["Paris","New York","Roma","Geneva","Zurich","Franckfort"];

// Progressive feeding writing 4M rows
$cst =[];

for ($i = 0 ; $i < 4000000 ; $i++) {
    $row = [
        "id" => [
            "__attr__" =>[
                "gender" => "M",
                "credit" => rand(10,100)
            ],
            rand(1288,57776)
        ] ,
        "address" => [
            "city" => $city[rand(0,5)],
            "zipCode" => rand (10000,50000),
            "null" => null

        ]
    ];
    $cst = $row;
    $xmW->append($cst ,"customers > customer #flag:blue, pipe:cool#");
}

// Here is for bulk feeding, you append array outside of loop
/*
$cst["customers"] = [];

for ($i = 0 ; $i < 2000 ; $i++) {
    $row = [
        "id" => [
            "__attr__" =>[
                "gender" => "M",
                "credit" => rand(10,100)
            ],
            rand(1288,57776)
        ] ,
        "address" => [
            "city" => $city[rand(0,5)],
            "zipCode" => rand (10000,50000),
            "null" => null

        ]
    ];
    $cst["customers"][] = $row;

}
$xmW->append($cst);
*/

// Here you can test an array containing special chars,
/*
$cst =  array(
    'data' => array(
        'root' => array(
            array(
                '@' => 'A & B: <OK>',
                'name' => 'C @ & D:@ <OK>',
                'sub1' => array(
                    'id' => 'E & F: OK',
                    'name' => 'G & H: OK',
                    'sub2' => array(
                        array(
                            '@id' => 'I & J: OK',
                            'name' => 'K & L: OK',
                            'sub3' => array(
                                '@id' => 'M & N: OK',
                                'name' => 'O & P: OK',
                                'sub4' => array(
                                    '@id' => 'Q & R: OK',
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
);
$xmW->commentOnError = true (default value )
$xmW->append($cst);
*/
$xmW->finalize();
```
**Output**
[![screen.png](https://s22.postimg.org/4hxa6zvu9/screen.png)](https://postimg.org/image/ocjbt4b1p/)
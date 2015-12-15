<?php

use LarryWeya\JsonForm\SchemaTransformer;

class SchemaTransformerTest extends PHPUnit_Framework_TestCase {

    public function testTransform()
    {
        $schema = [
            [
                'id' => 'name',
                'type' => 'text',
                'required' => true,
                'field_options' => []
            ],
            [
                'id' => 'age',
                'type' => 'number',
                'required' => true,
                'field_options' => [
                    'integer_only' => true
                ]
            ],
            [
                'id' => 'mobile_number',
                'type' => 'text',
                'required' => false,
                'field_options' => []
            ],
            [
                'id' => 'offspring',
                'type' => 'fieldset',
                'required' => true,
                'field_options' => [
                    'fields' => [
                        [
                            'id' => 'name',
                            'type' => 'text',
                            'required' => 'true',
                            'field_options' => []
                        ],
                        [
                            'id' => 'age',
                            'type' => 'number',
                            'required' => 'true',
                            'field_options' => [
                                'integer_only' => true
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $inputs = [
            "name" => "John",
            //"age"  => "32", // required value missing
            "mobile_number" => "", // edge-case blank value
            "offspring_name" => [
                "Baby 1",
                "Baby 2"
            ],
            //"photo" => \Symfony\Component\HttpFoundation\File\UploadedFile,
            "offspring_age" => [
                "3",
                "1"
            ]
        ];

        $values = (new SchemaTransformer)->transform($schema, $inputs);
        $this->assertEquals(
            [
                "name" => "John",
                //"age"  => "32",
                "offspring" => [
                    [
                        "name" => "Baby 1",
                        "age"  => 3
                    ],
                    [
                        "name" => "Baby 2",
                        "age"  => 1
                    ]
                ]
            ],
            $values
        );

    }

    public function testConvertToNativeTypeForInteger()
    {
        $field = [
            "id" => "age",
            "type" => "number",
            "field_options" => [
                "integer_only" => true
            ]
        ];
        $result = SchemaTransformer::convertToNativeType($field, "5");
        $this->assertEquals(5, $result);
    }

    public function testConvertToNativeTypeForNumber()
    {
        $field = [
            "id" => "age",
            "type" => "number",
            "field_options" => [
            ]
        ];
        $result = SchemaTransformer::convertToNativeType($field, "8.4");
        $this->assertEquals(8.4, $result);
    }

    public function testConvertToNativeTypeForCheckboxes()
    {
        $field = [
            "id" => "foods",
            "type" => "checkboxes",
            "field_options" => [
                "options" => [
                    [
                        "label" => "Ugali"
                    ],
                    [
                        "label" => "Chapati"
                    ]
                ]
            ]
        ];
        $result = SchemaTransformer::convertToNativeType($field, ["Ugali", "Chapati"]);
        $this->assertEquals(["Ugali", "Chapati"], $result);
    }
}
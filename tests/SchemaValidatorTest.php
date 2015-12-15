<?php

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Illuminate\Validation\Factory as ValidatorFactory;

use LarryWeya\JsonForm\SchemaValidator;

class SchemaValidatorTest extends PHPUnit_Framework_TestCase {

    protected $validatorFactory;

    protected $schema = [
        [
            "id" => "name",
            "type" => "text",
            "required" => true,
            "field_options" => []
        ],
        [
            "id" => "age",
            "type" => "number",
            "required" => true,
            "field_options" => [
                "integer_only" => true
            ]
        ],
        [
            "id" => "offspring",
            "type" => "fieldset",
            "required" => "true",
            "field_options" => [
                "fields" => [
                    [
                        "id" => "name",
                        "type" => "text",
                        "required" => true,
                        "field_options" => []
                    ],
                    [
                        "id" => "age",
                        "type" => "number",
                        "required" => true,
                        "field_options" => [
                            "integer_only" => true
                        ]
                    ],
                    [
                        "id" => "gender",
                        "type" => "radio",
                        "required" => true,
                        "field_options" => [
                            "integer_only" => true,
                            "options" => [
                                [
                                    "label" => "Male",
                                    "checked" => false
                                ],
                                [
                                    "label" => "Female",
                                    "checked" => false
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    protected $validInput = [
        "name" => "John",
        "age" => "23",
        "offspring_name" => ["Baby John", "Baby Jane"],
        "offspring_age" => ["3", "1"],
        "offspring_gender" => ["Male", "Female"],
    ];

    protected $invalidInput = [
        "name" => "John",
        "age" => "f",
        "offspring_name" => ["Baby John", "Baby Jane"],
        "offspring_age" => ["3", "1"],
        "offspring_gender" => ["Male", "Femal"],
    ];

    public function setUp() {
        $translator = new Translator('en_US', new MessageSelector());
        $this->validatorFactory = new ValidatorFactory($translator);
    }

    public function testRulesForNumberType()
    {
        $field = [
            'id' => 'price',
            'type' => 'number',
            'required' => true
        ];

        $validators = SchemaValidator::rulesForNumberType($field);
        $this->assertContains('numeric', $validators);
    }

    public function testRulesForIntegerType()
    {
        $field = [
            'id' => 'age',
            'type' => 'number',
            'required' => true,
            'field_options' => [
                'integer_only' => true
            ]
        ];

        $validators = SchemaValidator::rulesForNumberType($field);
        $this->assertContains('integer', $validators);
    }

    public function testRulesForDropdownWhenValueIsNotSet()
    {
        $field = [
            'id' => 'foods',
            'type' => 'dropdown',
            'required' => true,
            'field_options' => [
                'options' => [
                    [
                        "label" => "National ID",
                        "checked" => false
                    ],
                    [
                        "label" => "Alien ID",
                        "checked" => false
                    ]
                ]
            ]
        ];

        $validators = SchemaValidator::rulesForDropdownType($field);
        $this->assertContains('in:National ID,Alien ID', $validators);
    }

    public function testRulesForDropdownWhenValueIsSet()
    {
        $field = [
            'id' => 'foods',
            'type' => 'dropdown',
            'required' => true,
            'field_options' => [
                'options' => [
                    [
                        "label" => "National ID",
                        "value" => 'national_id',
                        "checked" => false
                    ],
                    [
                        "label" => "Alien ID",
                        "value" => 'alien_id',
                        "checked" => false
                    ]
                ]
            ]
        ];

        $validators = SchemaValidator::rulesForDropdownType($field);
        $this->assertContains('in:national_id,alien_id', $validators);
    }

    public function testRequiredValidator()
    {
        $schema = [
            [
                'id' => 'age',
                'type' => 'number',
                'required' => true
            ]
        ];

        $rules = SchemaValidator::rulesFromSchema($schema);
        $this->assertContains('required', $rules['age']);
    }

    /**
     * Test that rulesFromSchema appends square brackets to dropdown field type
     */
    public function testAppendsBracketsToDropdown()
    {
        $schema = [
            [
                'id' => 'age',
                'type' => 'number',
                'required' => true
            ],
            [
                'id' => 'colors',
                'type' => 'dropdown',
                'required' => true,
                'field_options' => [
                    'options' => [
                        [
                            'label' => 'red',
                            'checked' => false
                        ],
                        [
                            'label' => 'blue',
                            'checked' => false
                        ]
                    ]
                ]
            ],
        ];

        $rules = SchemaValidator::rulesFromSchema($schema);
        $this->assertArrayHasKey('colors[]', $rules);
        $this->assertArrayHasKey('age', $rules);
    }

    /**
     * Test rule generation when we have nested fields
     */
    public function testRulesFromSchemaWhenMultiLevel()
    {
        $schema = [
            [
                'id' => 'age',
                'type' => 'number',
                'required' => true,
                'field_options' => [
                    'integer_only' => true
                ]
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

        $rules = SchemaValidator::rulesFromSchema($schema);
        $this->assertEquals(
            [
                'age' => ['required', 'integer'],
                "offspring" => [
                    "name" => ["required"],
                    "age" => ["required", "integer"],
                ]
            ], $rules);
    }

    public function testValidateWhenValid()
    {
        $validator = new SchemaValidator($this->validatorFactory);
        list($passed, $validator) = $validator->validate($this->schema, $this->validInput);
        $this->assertTrue($passed);
        $this->assertEquals(
        [
            // this is what an error free nested validation looks like
            "offspring" => [
                (new \stdClass),
                (new \stdClass),
            ]
        ],
        $validator->messages()->toArray());
    }

    public function testValidateWhenInvalid()
    {
        $rules = [
            "name" => ['required'],
            "age" => ["required", "integer"],
            "offspring" => [
                "name" => ["required"],
                "age" => ["required", "integer"],
                "gender" => ["required", "in:Male,Female"],
            ]
        ];

        /*Validator::shouldReceive('make')
            ->with(
                $input,
                [
                    "name" => ["required"],
                    "age" => ["required", "integer"]
                ]
            )
            ->once();

        Validator::shouldReceive('make')
            ->with(
                [
                    "offspring_name" => "Baby John",
                    "offspring_age" => "h",
                    "offspring_gender" => "Mal"
                ],
                [
                    "offspring_name" => ["required"],
                    "offspring_age" => ["required", "integer"],
                    "offspring_gender" => ["required", "in:Male,Female"]
                ]
            )
            ->once();

        Validator::shouldReceive('make')
            ->with(
                [
                    "offspring_name" => "Baby Jane",
                    "offspring_age" => "1",
                    "offspring_gender" => "Femal"
                ],
                [
                    "offspring_name" => ["required"],
                    "offspring_age" => ["required", "integer"],
                    "offspring_gender" => ["required", "in:Male,Female"],
                ]
            )
            ->once();*/

        $validator = new SchemaValidator($this->validatorFactory);
        list($passed, $validator) = $validator->validate($this->schema, $this->invalidInput);
        $this->assertFalse($passed);
        $this->assertEquals(
            [
                "age" => ["validation.integer"],
                "offspring" => [
                    (new \stdClass),
                    [
                        "gender" => ["validation.in"]
                    ]
                ]
            ],
            $validator->messages()->toArray());
    }
}
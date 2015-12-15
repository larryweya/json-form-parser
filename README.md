# PHP json-form-parser

A PHP based validator and transformer based on [this](https://github.com/dobtco/formbuilder) schema.

## Installation
Install via composer by adding this repo's url to your composer.json repository section (create it if it doenst exist) and under your requires

```json
"repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/larryweya/json-form-parser"
    }
],
"require": {
  "larryweya/json-form-parser": "dev-master"
}
```

Run composer update

## Usage

To validate input data against a schema, you need:

 - A schema
```php
$schema = [
  [
    "id" => "name",
    "type" => "text"
    "required" => true,
    "field_options" => []
  ],
  [
    "id" => "age",
    "type" => "number"
    "required" => true,
    "field_options" => [
      "integer_only" => true
    ]
  ]
]
```
 - Input values to validate against

```php
$input = [
  "name" => "John Smith",
  "age" => "" // age is required
]
```

You can now instantiate and call the validator

```php
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;

...

$translator = new Translator('en_US', new MessageSelector());
$validatorFactory = new ValidatorFactory($translator);
$schemaValidator = new SchemaValidator($validatorFactory);
$validator = $schemaValidator->validate($schema, $input);

if($validator->passes())
{
    // all good, you can now transofrm to native types to store as json
    $schemaTransformer = new SchemaTransformer();
    $values = $schemaTransformer->transform($schema, $input)
}
else
{
    // return errors
    return $validator->errors();
}
```

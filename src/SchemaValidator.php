<?php namespace LarryWeya\JsonForm;

use Illuminate\Validation\Factory as ValidatorFactory;

class SchemaValidator {

    protected $validatorFactory;

    /**
     * SchemaValidator constructor.
     * @param ValidatorFactory $validatorFactory
     */
    public function __construct(ValidatorFactory $validatorFactory)
    {
        $this->validatorFactory = $validatorFactory;
    }

    /**
     * @param $field
     * @return array
     */
    public static function rulesForNumberType($field)
    {
        $validators = [];
        if(isset($field['field_options'])
            && isset($field['field_options']['integer_only'])
            && $field['field_options']['integer_only'])
            $validators[] = 'integer';
        else
            $validators[] = 'numeric';
        return $validators;
    }

    /**
     * @param $field
     * @return array
     */
    public static function rulesForEmailType($field)
    {
        return ['email'];
    }

    /**
     * @param $field
     * @return array
     * @todo: perhaps have a field_option with the expected format
     */
    public static function rulesForDateType($field)
    {
        // @todo: check for date format within field options
        return ['date'];
    }

    /**
     * Rules shared by dropdowns and radios
     */
    private static function rulesForOptions($field)
    {
        if(isset($field['field_options']['options']))
        {
            $options = array_reduce(
                $field['field_options']['options'],
                function ($carry, $option) {
                    $carry[] = isset($option['value'])?$option['value']:$option['label'];
                    return $carry;
                }, []);

            return ['in:' . implode(",", $options)];
        }
        else
        {
            return [];
        }
    }

    /**
     * @param $field
     * @return array
     */
    public static function rulesForDropdownType($field)
    {
        return static::rulesForOptions($field);
    }

    /**
     * @param $field
     * @return array
     */
    public static function rulesForRadioType($field)
    {
        return static::rulesForOptions($field);
    }

    /**
     * Return an array of validation rules from the type
     *
     * @param array $field
     * @return array
     */
    public static function rulesByType($field)
    {
        // find the function to handle our type
        $methodName = sprintf("rulesFor%sType", ucfirst($field['type']));
        if(method_exists(get_class(), $methodName))
        {
            $validators = call_user_func(array(
                get_class(), $methodName), $field);
        }
        else
            $validators = [];

        return $validators;
    }

    /**
     * Return the validation rules from the provided schema
     *
     * @param array $schema
     * @return array
     */
    public static function rulesFromSchema(array $schema)
    {
        return array_reduce($schema, function ($carry, $field)  {
            $validators = [];

            if($field["type"] === "fieldset")
            {
                $validators = static::rulesFromSchema($field['field_options']['fields']);
            }
            else
            {
                // if required add a required rule
                if(isset($field['required']) && $field['required'])
                    $validators[] = 'required';

                // rules by the type
                $validators = array_merge($validators, static::rulesByType($field));
            }

            // if field is a dropdown, client will append square brackets to make it an array, do the same to the rules
            if($field["type"] === "dropdown")
                $id = $field["id"] . "[]";
            else
                $id = $field["id"];

            // prepend dot delimited parent ids, a dot and sq. braces to $id
            /*if(count($parents) > 0)
                $id = implode("_", $parents) . "_" .$id;*/

            // if a fieldset, simple apend validators without an id
            /*if($field["type"] === "fieldset")
                $carry = array_merge($carry, $validators);
            else*/
            $carry[$id] = $validators;

            return $carry;
        }, []);
    }

    /**
     * Return the list of fieldsets from the schema
     * @param array $schema
     * @param boolean $withFieldsets whether to return those with fieldsets or not
     * @return array
     */
    public static function filterFieldsets(array $schema, $withFieldsets = true)
    {
        return array_filter($schema, function ($field) use($withFieldsets) {
            if($withFieldsets)
                return $field["type"] === "fieldset";
            else
                return $field["type"] !== "fieldset";
        });
    }

    /**
     * Return  a lost of ids from a list of fields
     * @param array $fields
     * @return array
     */
    public static function mapFieldIds(array $fields)
    {
        return array_map(function ($field) {
            // if we have a parents, prefix with parentIds concat. with _
            return $field['id'];
        }, $fields);
    }

    /**
     * @param $fieldSetId
     * @param $childSchemaId
     * @return string
     */
    public static function childKey($fieldSetId, $childSchemaId)
    {
        return $fieldSetId . "_" . $childSchemaId;
    }

    /**
     * @param array $schema
     * @param array $inputs
     * @param array $rules
     * @return \Illuminate\Validation\Validator
     */
    public function validate(array $schema, array $inputs, array $rules = null)
    {
        $passed = true;

        $rules = $rules?:static::rulesFromSchema($schema);

        // filter rules for fieldsets
        $fieldsets = static::filterFieldsets($schema, true);
        $fieldsetsIds = static::mapFieldIds($fieldsets);

        // filter non-fieldset rules
        $fieldsetRules = array_intersect_key($rules, array_flip($fieldsetsIds));

        // filter rules for non fieldsets
        $nonFieldsets = static::filterFieldsets($schema, false);
        $nonFieldsetIds = static::mapFieldIds($nonFieldsets);

        // filter non-fieldset rules
        $nonFieldsetRules = array_intersect_key($rules, array_flip($nonFieldsetIds));

        // validate non-fieldset fields
        $validator = $this->validatorFactory->make($inputs, $nonFieldsetRules);

        $passed = $validator->passes() && $passed;

        // foreach fieldset rule, recurse
        foreach($fieldsetRules as $fieldSetId => $childRules)
        {
            // find the current fieldset in list of fieldsets - use array_values to reset index to zero
            $thisFieldset = array_values(
                array_filter($fieldsets, function ($fieldset) use($fieldSetId) {
                    return $fieldset['id'] === $fieldSetId;
                }))[0]; // @todo: is it possible that the fieldset doesnt exist

            $childSchemas = $thisFieldset["field_options"]["fields"];
            /*$childSchemas = array_map(function ($childSchema) use($fieldSetId) {
                $childSchema["id"] = static::childKey($fieldSetId, $childSchema["id"]);
                return $childSchema;
            }, $childSchemas);*/

            // build a name for any of the child fields - lets use the first; in the form $fieldSetId "." $childId "." []
            // @todo: factor in parents for recursive calls more than 2 levels deep
            $childId0Key = static::childKey($fieldSetId, $childSchemas[0]["id"]);

            $numChildInputs = isset($inputs[$childId0Key])?count($inputs[$childId0Key]):0;

            // foreach child input, run validation
            $childMessages[$fieldSetId] = [];
            for($i = 0; $i < $numChildInputs; $i++)
            {
                $childInputs = [];
                foreach($childSchemas as $childSchema)
                {
                    $childKey = static::childKey($fieldSetId, $childSchema["id"]);
                    $childInputs[$childSchema["id"]] = $inputs[$childKey][$i];
                }
                list($childPasses, $childValidator) = $this->validate($childSchemas, $childInputs, $childRules);
                $passed = $childPasses && $passed;

                // get array so we can check if its empty and convert to a stdClass for proper json encoding
                $childMessageArray = $childValidator->messages()->toArray();

                // merge messages
                $childMessages[$fieldSetId][] = empty($childMessageArray)?(new \stdClass()):$childMessageArray;
            }
            $validator->messages()->merge($childMessages);

        }

        return [$passed, $validator];
    }


}
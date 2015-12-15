<?php namespace LarryWeya\JsonForm;

class SchemaTransformer {

    /**
     * Transform form input values into a nice array, converting native types - most useful for nested values tha are tokenized with an underscore
     *
     * @param array $schema
     * @param array $inputs
     * @param int $index of the current run
     * @param array $parentIds of the field if any
     * @return array
     */
    public function transform(array $schema, array $inputs, $index = 0, $parentIds = [])
    {
        $values = [];
        foreach($schema as $childIndex => $field)
        {
            $childParents = array_merge($parentIds, [$field["id"]]);
            $fieldId = implode("_", $childParents);

            if($field["type"] === "fieldset")
            {
                // determine the number of values under this fieldset
                $childSchema = $field["field_options"]["fields"];

                if(count($childSchema) > 0)
                {
                    $childFieldId0 = $childSchema[0]["id"];
                    if(isset($inputs[$fieldId . "_" . $childFieldId0]))
                    {
                        $numValues = count($inputs[$fieldId . "_" . $childFieldId0]);

                        for($i = 0; $i < $numValues; $i++)
                        {
                            $values[$field["id"]][] = $this->transform($childSchema, $inputs, $i, $childParents);
                        }
                    }
                }
                else
                    \Log::warning("Fieldset without child fields");
            }
            else
            {
                // check if value is set and transform to native type
                if(isset($inputs[$fieldId]) && !!$inputs[$fieldId])
                {
                    if(count($parentIds) > 0)
                    {
                        $input = $inputs[$fieldId][$index];
                    }
                    else
                    {
                        $input = $inputs[$fieldId];
                    }

                    // set value
                    $values[$field["id"]] = static::convertToNativeType($field, $input);
                }
            }
        }
        return $values;
    }

    /**
     * Convert the value to the native type
     * Assumes data has already been validated
     */
    public static function convertToNativeType($field, $value)
    {
        switch($field["type"])
        {
            case "number":
                if(isset($field["field_options"]["integer_only"]) && $field["field_options"]["integer_only"])
                    return intval($value);
                else
                    return floatval($value);
                break; // defensive but not necessary
            case "date":  // @todo: use a format attribute within field options accordig to date_parse_from_format function
                return $value;
                break;
            // @todo: file
            default:
                return $value;
        }
    }

}
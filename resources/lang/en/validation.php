<?php

declare(strict_types=1);

/**
 * Validation translations (English)
 * 
 * PHP format allows for more complex structures and logic
 */

return [
    'required' => 'The :field field is required.',
    'required_if' => 'The :field field is required when :other is :value.',
    'required_unless' => 'The :field field is required unless :other is in :values.',
    'required_with' => 'The :field field is required when :values is present.',
    'required_with_all' => 'The :field field is required when :values are present.',
    'required_without' => 'The :field field is required when :values is not present.',
    'required_without_all' => 'The :field field is required when none of :values are present.',
    
    'email' => 'The :field must be a valid email address.',
    'url' => 'The :field must be a valid URL.',
    'ip' => 'The :field must be a valid IP address.',
    'ipv4' => 'The :field must be a valid IPv4 address.',
    'ipv6' => 'The :field must be a valid IPv6 address.',
    
    'min' => [
        'numeric' => 'The :field must be at least :min.',
        'file' => 'The :field must be at least :min kilobytes.',
        'string' => 'The :field must be at least :min characters.',
        'array' => 'The :field must have at least :min items.',
    ],
    
    'max' => [
        'numeric' => 'The :field must not be greater than :max.',
        'file' => 'The :field must not be greater than :max kilobytes.',
        'string' => 'The :field must not be greater than :max characters.',
        'array' => 'The :field must not have more than :max items.',
    ],
    
    'between' => [
        'numeric' => 'The :field must be between :min and :max.',
        'file' => 'The :field must be between :min and :max kilobytes.',
        'string' => 'The :field must be between :min and :max characters.',
        'array' => 'The :field must have between :min and :max items.',
    ],
    
    'size' => [
        'numeric' => 'The :field must be :size.',
        'file' => 'The :field must be :size kilobytes.',
        'string' => 'The :field must be :size characters.',
        'array' => 'The :field must contain :size items.',
    ],
    
    'alpha' => 'The :field must only contain letters.',
    'alpha_dash' => 'The :field must only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'The :field must only contain letters and numbers.',
    'array' => 'The :field must be an array.',
    'ascii' => 'The :field must only contain single-byte alphanumeric characters and symbols.',
    
    'before' => 'The :field must be a date before :date.',
    'before_or_equal' => 'The :field must be a date before or equal to :date.',
    'after' => 'The :field must be a date after :date.',
    'after_or_equal' => 'The :field must be a date after or equal to :date.',
    
    'boolean' => 'The :field field must be true or false.',
    'confirmed' => 'The :field confirmation does not match.',
    'date' => 'The :field is not a valid date.',
    'date_equals' => 'The :field must be a date equal to :date.',
    'date_format' => 'The :field does not match the format :format.',
    
    'different' => 'The :field and :other must be different.',
    'digits' => 'The :field must be :digits digits.',
    'digits_between' => 'The :field must be between :min and :digits digits.',
    'distinct' => 'The :field field has a duplicate value.',
    
    'in' => 'The selected :field is invalid.',
    'in_array' => 'The :field field does not exist in :other.',
    'integer' => 'The :field must be an integer.',
    'json' => 'The :field must be a valid JSON string.',
    'numeric' => 'The :field must be a number.',
    'regex' => 'The :field format is invalid.',
    'same' => 'The :field and :other must match.',
    'string' => 'The :field must be a string.',
    'timezone' => 'The :field must be a valid timezone.',
    'unique' => 'The :field has already been taken.',
    
    'uploaded' => 'The :field failed to upload.',
    'mimes' => 'The :field must be a file of type: :values.',
    'mimetypes' => 'The :field must be a file of type: :values.',
    'image' => 'The :field must be an image.',
    'file' => 'The :field must be a file.',
    
    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],
    
    'attributes' => [
        'email' => 'email address',
        'password' => 'password',
        'name' => 'name',
        'username' => 'username',
        'phone' => 'phone number',
    ],
];

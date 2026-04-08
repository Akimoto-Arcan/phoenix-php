<?php

namespace Phoenix;

/**
 * Input Validation Class
 *
 * Provides comprehensive validation rules for form inputs
 * and API request validation.
 */

class Validator {
    private $data = [];
    private $rules = [];
    private $errors = [];
    private $validated = false;

    /**
     * Create new validator instance
     */
    private function __construct($data, $rules) {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Static factory method to create validator
     */
    public static function make($data, $rules) {
        return new self($data, $rules);
    }

    /**
     * Validate the data against rules
     */
    public function validate() {
        $this->errors = [];
        $this->validated = true;

        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return count($this->errors) === 0;
    }

    /**
     * Apply a single validation rule
     */
    private function applyRule($field, $value, $rule) {
        // Parse rule and parameters
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, "The {$field} field is required.");
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} must be a valid email address.");
                }
                break;

            case 'min':
                $min = $params[0] ?? 0;
                // Skip min validation if value is empty (only validate if provided)
                if (empty($value) && $value !== '0' && $value !== 0) {
                    break;
                }
                if (is_numeric($value) && $value < $min) {
                    $this->addError($field, "The {$field} must be at least {$min}.");
                } elseif (is_string($value) && strlen($value) < $min) {
                    $this->addError($field, "The {$field} must be at least {$min} characters.");
                }
                break;

            case 'max':
                $max = $params[0] ?? 0;
                // Skip max validation if value is empty (only validate if provided)
                if (empty($value) && $value !== '0' && $value !== 0) {
                    break;
                }
                if (is_numeric($value) && $value > $max) {
                    $this->addError($field, "The {$field} must not exceed {$max}.");
                } elseif (is_string($value) && strlen($value) > $max) {
                    $this->addError($field, "The {$field} must not exceed {$max} characters.");
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "The {$field} must be a number.");
                }
                break;

            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "The {$field} must be an integer.");
                }
                break;

            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, "The {$field} may only contain letters.");
                }
                break;

            case 'alphanumeric':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, "The {$field} may only contain letters and numbers.");
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "The {$field} must be a valid URL.");
                }
                break;

            case 'in':
                if (!empty($value) && !in_array($value, $params)) {
                    $this->addError($field, "The {$field} must be one of: " . implode(', ', $params));
                }
                break;

            case 'regex':
                $pattern = $params[0] ?? '';
                if (!empty($value) && !preg_match($pattern, $value)) {
                    $this->addError($field, "The {$field} format is invalid.");
                }
                break;

            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, "The {$field} must be a valid date.");
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                $confirmValue = $this->data[$confirmField] ?? null;
                if ($value !== $confirmValue) {
                    $this->addError($field, "The {$field} confirmation does not match.");
                }
                break;
        }
    }

    /**
     * Add an error message
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Check if validation has errors
     */
    public function hasErrors() {
        return count($this->errors) > 0;
    }

    /**
     * Get all errors
     */
    public function errors() {
        return $this->errors;
    }

    /**
     * Get errors for specific field
     */
    public function getErrors($field) {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get first error for field
     */
    public function firstError($field) {
        $errors = $this->getErrors($field);
        return $errors[0] ?? null;
    }

    /**
     * Get validated data (only fields that passed validation)
     */
    public function validated() {
        if (!$this->validated) {
            throw new \Exception('Validation has not been run yet.');
        }

        if ($this->hasErrors()) {
            throw new \Exception('Cannot get validated data when validation has errors.');
        }

        $validated = [];
        foreach ($this->rules as $field => $rule) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    /**
     * Get validated data or throw exception with errors
     */
    public function validatedOrFail() {
        if ($this->hasErrors()) {
            $errorMessages = [];
            foreach ($this->errors as $field => $errors) {
                $errorMessages[] = implode(' ', $errors);
            }
            throw new \Exception(implode(' ', $errorMessages));
        }

        return $this->validated();
    }
}

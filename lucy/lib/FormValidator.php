<?php
/**
 * FormValidator
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class FormValidator
{
    public $valid   = array();
    public $invalid = array();
    public $missing = array();

    /**
     * __construct 
     * 
     * @return void
     */
    public function __construct () {}

    /**
     * validate 
     * 
     * Returns true on success, array of errors otherwise.
     * 
     * @param array $input 
     * @param array $profile 
     * 
     * @return boolean/array
     */
    public function validate ($input, $profile)
    {
        if (isset($_SESSION['form_errors']))
        {
            unset($_SESSION['form_errors']);
        }

        $this->valid   = array();
        $this->invalid = array();
        $this->missing = array();

        // Get constraints
        $constraints = isset($profile['constraints']) ? $profile['constraints'] : $profile;

        // Loop through constraint fields in profile
        foreach ($constraints as $fieldName => $options)
        {
            $bad = false;

            // Required
            if (isset($options['required']))
            {
                if (!isset($input[$fieldName]) || strlen($input[$fieldName]) == 0)
                {
                    $this->missing[] = $fieldName;
                    $bad = true;
                    continue;
                }
            }

            // Goto next field if no data was passed
            if (!isset($input[$fieldName]))
            {
                continue;
            }

            $value = $input[$fieldName];

            // Regex / Format
            if (isset($options['format']))
            {
                if (strlen($value) > 0)
                {
                    if (preg_match($options['format'], $value) === 0)
                    {
                        $this->invalid[] = $fieldName;
                        $bad = true;
                        continue;
                    }
                }
            }

            // Integers
            if (isset($options['integer']))
            {
                if (strlen($value) > 0)
                {
                    if (!is_int($value) && !ctype_digit($value))
                    {
                        $this->invalid[] = $fieldName;
                        $bad = true;
                        continue;
                    }
                }
            }

            // Length
            if (isset($options['length']))
            {
                if (strlen($value) > $options['length'])
                {
                    $this->invalid[] = $fieldName;
                    $bad = true;
                    continue;
                }
            }

            // Acceptance
            if (isset($options['acceptance']))
            {
                if (strlen($value) == 0 || $value == 'off')
                {
                    $this->invalid[] = $fieldName;
                    $bad = true;
                    continue;
                }
            }

            if (!$bad)
            {
                $this->valid[] = $fieldName;
            }
        }

        $errors = array();

        if (count($this->missing) > 0 || count($this->invalid) > 0)
        {
            $this->updateNames($profile);
        }

        if (count($this->missing) > 0)
        {
            foreach ($this->missing as $field)
            {
                $errors[] = sprintf(_('%s is missing.'), $field);

                $_SESSION['form_errors']['fields'][$field] = 'required';
            }
        }

        if (count($this->invalid) > 0)
        {
            foreach ($this->invalid as $field)
            {
                $errors[] = sprintf(_('%s is invalid.'), $field);

                $_SESSION['form_errors']['fields'][$field] = 'invalid';
            }
        }

        if (count($errors) > 0)
        {
            $_SESSION['form_errors']['errors'] = $errors;

            return $errors;
        }

        return true;
    }

    /**
     * getJsValidation 
     * 
     * @param array $profile 
     * 
     * @return string
     */
    public function getJsValidation ($profile)
    {
        // TODO - need a js script to handle this
        return;

        $js  = "\n";
        $js .= '<script type="text/javascript" src="ui/js/livevalidation.js"></script>';
        $js .= '<script type="text/javascript">';

        // Get constraints
        $constraints = isset($profile['constraints']) ? $profile['constraints'] : $profile;

        foreach ($constraints as $fieldName => $options)
        {
            $js .= "\n";
            $js .= 'var f'.$fieldName.' = new LiveValidation(\''.$fieldName.'\', { onlyOnSubmit: true });'."\n";

            // Required
            if (isset($options['required']))
            {
                $message = $this->getConstraintMessage($profile, $fieldName, 'required');

                if ($message === false)
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Presence);'."\n";
                }
                // Overwrite failure message
                else
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Presence, { failureMessage: "'.$message.'" });'."\n";
                }
            }

            // Regex / Format
            if (isset($options['format']))
            {
                $message = $this->getConstraintMessage($profile, $fieldName, 'format');

                if ($message === false)
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Format, { pattern: '.$options['format'].' });'."\n";
                }
                // Overwrite failure message
                else
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Format, { pattern: '.$options['format'].', failureMessage: "'.$message.'" });'."\n";
                }
            }

            // Integers
            if (isset($options['integer']))
            {
                $message = $this->getConstraintMessage($profile, $fieldName, 'integer');

                if ($message === false)
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Numericality, { onlyInteger: true });'."\n";
                }
                // Overwrite failure message
                else
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Numericality, { onlyInteger: true, failureMessage: "'.$message.'" });'."\n";
                }
                
            }

            // Length
            if (isset($options['length']))
            {
                $message = $this->getConstraintMessage($profile, $fieldName, 'length');

                if ($message === false)
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Length, { is: '.$options['length'].' });'."\n";
                }
                // Overwrite failure message
                else
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Length, { is: '.$options['length'].', failureMessage: "'.$message.'" });'."\n";
                }
            }

            // Acceptance
            if (isset($options['acceptance']))
            {
                $message = $this->getConstraintMessage($profile, $fieldName, 'acceptance');

                if ($message === false)
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Acceptance);'."\n";
                }
                // Overwrite failure message
                else
                {
                    $js .= 'f'.$fieldName.'.add(Validate.Acceptance, { failureMessage: "'.$message.'" });'."\n";
                }
            }
        }

        $js .= '</script>';

        return $js;
    }

    /**
     * getConstraintMessage 
     * 
     * Will return the constraint message for the given constraint name,
     * or false if no message is found.
     * 
     * @param array  $profile 
     * @param string $fieldName 
     * @param string $contraintName
     * 
     * @return boolean/string
     */
    private function getConstraintMessage ($profile, $fieldName, $constraintName)
    {
        // Overriding the failure message?
        if (isset($profile['messages']) && isset($profile['messages']['constraints'][$fieldName]))
        {
            $constraintMessages = $profile['messages']['constraints'][$fieldName];

            // Message could be specific to a constraint or global to all
            $message = (is_array($constraintMessages) && isset($constraintMessages[$contraintName]))
                     ? $constraintMessages[$contraintName] 
                     : $constraintMessages;

            return cleanOutput($message);
        }

        return false;
    }

    /**
     * updateName 
     * 
     * Turns the names in invalid and missing array from the name of the
     * field into the message supplied to represent that field.
     * 
     * @param string $profile 
     * 
     * @return void
     */
    private function updateNames ($profile)
    {
        // Update field names with messages if available
        if (isset($profile['messages']) && isset($profile['messages']['names']))
        {
            foreach (array('missing', 'invalid') as $type)
            {
                foreach ($this->{$type} as $key => $field)
                {
                    if (isset($profile['messages']['names'][$field]))
                    {
                        $this->{$type}[$key] = $profile['messages']['names'][$field];
                    }
                }
            }
        }
    }
}

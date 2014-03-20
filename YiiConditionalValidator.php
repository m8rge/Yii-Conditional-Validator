<?php
/** @noinspection PhpUndefinedClassInspection */

/**
 * YiiConditionalValidator
 * If-then validation rules on Yii Framework using core validators.
 *
 * Example:
 * <code>
 * // Whole If and Then branches must be present in file only once, so
 * // Not allowed multiple attributes
 * // here ↓
 * array('type', 'ext.validators.YiiConditionalValidator',
 *      'if' => array(
 *          array('type', 'compare', 'compareValue' => 1),
 *      ),
 *      'then' => array(
 *          array('phone', 'match', 'pattern' => '/^7/', 'message' => 'phone must starts with 7'),
 *          array('name', 'length', 'max' => 255),
 *      )
 * ),
 * </code>
 *
 * @author Sidney Lins <solucoes@wmaior.com>
 * @author Andrey Putilov <to.merge@gmail.com>
 * @copyright Copyright &copy; 2011 Sidney Lins
 * @version 1.1.0
 * @license New BSD Licence
 */
class YiiConditionalValidator extends CValidator
{
    public $if = array();
    public $then = array();

    /**
     * @param CModel $object
     * @param string $attribute
     */
    protected function validateAttribute($object, $attribute)
    {
        $noErrorsOnIfRules = $this->runValidators($object, $this->if, true);

        if ($noErrorsOnIfRules) {
            $this->runValidators($object, $this->then);
        }
    }

    /**
     * Creates and executes each validator based on $validatorsData.
     *
     * @param CModel $object
     * @param array $rules
     * @param boolean $discardErrorsAfterCheck Useful to allow discard validation
     * errors in "if" rules but not in "then" rules.
     * @return boolean error while validating model
     */
    protected function runValidators($object, $rules, $discardErrorsAfterCheck = false) {
        $validators = $this->createValidators($object, $rules);

        $errorsBackup = $object->getErrors();
        $object->clearErrors();
        foreach ($validators as $validator) {
            $validator->validate($object);

            foreach ($validator->attributes as $attribute) {
                if ($object->hasErrors($attribute)) {
                    if ($discardErrorsAfterCheck) {
                        $object->clearErrors();
                        $object->addErrors($errorsBackup);
                        return false;
                    }
                }
            }
        }

        $object->addErrors($errorsBackup);
        return true;
    }

    /**
     * @param CModel $model
     * @param array $rules
     * @return CValidator[]
     * @throws CException
     */
    public function createValidators($model, $rules)
    {
        static $existingValidators = array();

        $cacheKey = md5(serialize($rules) . serialize($model));
        if (empty($existingValidators[$cacheKey])) {
            $validators = array();
            foreach ($rules as $rule) {
                if (isset($rule[0], $rule[1])) // attributes, validator name
                {
                    $validators[] = self::createValidator($rule[1], $model, $rule[0], array_slice($rule, 2));
                } else {
                    /** @noinspection PhpUndefinedClassInspection */
                    throw new CException(
                        Yii::t(
                            'yii',
                            '{class} has an invalid validation rule. The rule must specify attributes to be validated and the validator name.',
                            array('{class}' => get_class($model))
                        )
                    );
                }
            }

            $existingValidators[$cacheKey] = $validators;
        }

        return $existingValidators[$cacheKey];
    }

    public function clientValidateAttribute($object, $attribute)
    {
        $ifValidators = $this->createValidators($object, $this->if);
        $ifJs = '';
        $thenValidators = $this->createValidators($object, $this->then);
        $thenJs = '';

        foreach ($ifValidators as $validator) {
            foreach ($validator->attributes as $ifAttribute) {
                $js = $validator->clientValidateAttribute($object, $ifAttribute);

                if (!preg_match('/if\s*?\((.+)\)\s*?\{/s', $js, $matches)) {
                    throw new CException(
                        'Error in YiiConditionalValidator: can\'t extract js condition for "if" validator'
                    );
                }
                $if = $matches[1];
                $if = preg_replace(
                    '/\bvalue\b/',
                    'jQuery("#' . CHtml::activeId($object, $ifAttribute) . '").val()',
                    $if
                );
                $ifJs .= $if;
            }
        }

        foreach ($thenValidators as $validator) {
            foreach ($validator->attributes as $thenAttribute) {
                $then = $validator->clientValidateAttribute($object, $thenAttribute);
                $then = preg_replace(
                    '/\bvalue\b/',
                    'jQuery("#' . CHtml::activeId($object, $thenAttribute) . '").val()',
                    $then
                );
                $thenJs .= $then;
            }
        }

        return "\nif($ifJs){{$thenJs}}\n";
    }
}

<?php

declare(strict_types=1);

namespace drahil\Tailor\PsySH\Matchers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Trait for matchers that need to filter out Eloquent Models.
 *
 * @method mixed getVariable(string $name)
 * @method string getNamespaceAndClass(array $tokens)
 */
trait FiltersEloquentModels
{
    /**
     * Check if tokens represent static access to an Eloquent Model class.
     *
     * @param array<mixed> $tokens
     * @return bool
     */
    protected function isModelStaticAccess(array $tokens): bool
    {
        $tokensClone = $tokens;

        $firstToken = array_pop($tokensClone);

        if (self::tokenIs($firstToken, self::T_STRING)) {
            $operatorToken = array_pop($tokensClone);
        } else {
            $operatorToken = $firstToken;
        }

        if (! self::tokenIs($operatorToken, self::T_DOUBLE_COLON)) {
            return false;
        }

        $className = $this->getNamespaceAndClass($tokensClone);

        if (empty($className) || ! class_exists($className)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($className);

            return $reflection->isSubclassOf(Model::class) || $reflection->getName() === Model::class;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if tokens represent instance access to an Eloquent Model object.
     *
     * @param array<mixed> $tokens
     * @return bool
     */
    protected function isModelInstanceAccess(array $tokens): bool
    {
        $tokensClone = $tokens;

        $firstToken = array_pop($tokensClone);

        if (self::tokenIs($firstToken, self::T_STRING)) {
            $operatorToken = array_pop($tokensClone);
        } else {
            $operatorToken = $firstToken;
        }

        if (! self::tokenIs($operatorToken, self::T_OBJECT_OPERATOR)) {
            return false;
        }

        $objectToken = array_pop($tokensClone);

        if (! is_array($objectToken)) {
            return false;
        }

        $objectName = str_replace('$', '', $objectToken[1]);

        try {
            $object = $this->getVariable($objectName);

            return $object instanceof Model;
        } catch (InvalidArgumentException|Exception $e) {
            return false;
        }
    }
}

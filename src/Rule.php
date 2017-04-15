<?php

namespace TiMacDonald;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule as LaravelRule;

class Rule
{
    const SIMPLE_RULES = [
        'accepted',
        'active_url', // custom
        'alpha', // custom
        'alpha_dash', // custom
        'alpha_num', // custom
        'array',
        'boolean',
        'character', // custom
        'confirmed',
        'date',
        'distinct',
        'email', // custom
        'file', // custom
        'filled',
        'image', // custom
        'integer', // custom
        'ip',
        'json', // custom
        'nullable',
        'numeric', // custom
        'present',
        'required',
        'string', // custom
        'timezone',
        'url' // custom
    ];

    const RULES_WITH_ARGUMENTS = [
        'after',
        'before',
        'between',
        'date_format',
        'different',
        'digits',
        'digits_between',
        'in_array',
        'max',
        'mimetypes',
        'mimes',
        'min',
        'regex',
        'required_with',
        'required_with_all',
        'required_without',
        'required_without_all',
        'same',
        'size',
        'when' // custom
    ];

    const RULES_WITH_ID_AND_ARGUMENTS = [
        'required_if',
        'required_unless'
    ];

    const FLAGS = [
        'bail',
        'sometimes'
    ];

    const PROXIED_RULES = [
        'dimensions',
        'exists',
        'in',
        'not_in',
        'unique'
    ];

    protected $localRules = [];

    protected $proxiedRules = [];

    public static function __callStatic($method, $arguments)
    {
        return call_user_func_array([new static, $method], $arguments);
    }

    public function __call($method, $arguments)
    {
        $rule = Str::snake($method);

        if ($this->isLocalRule($rule)) {
            return $this->applyLocalRule($rule, $arguments);
        }

        if ($this->isProxyRule($rule)) {
            return $this->applyProxyRule($method, $arguments);

        }

        if ($this->canApplyToLatestProxyRule($method)) {
            return $this->applyToLatestProxyRule($method, $arguments);
        }

        throw new BadMethodCallException('Unable to handle or proxy the method '.$method.'(). If it is to be applied to a proxy rule, ensure it is called directly after the original proxy rule.');
    }

    protected function isLocalRule($rule)
    {
        return in_array($rule, static::SIMPLE_RULES)
            || in_array($rule, static::RULES_WITH_ARGUMENTS)
            || in_array($rule, static::RULES_WITH_ID_AND_ARGUMENTS)
            || in_array($rule, static::FLAGS);
    }

    protected function isProxyRule($rule)
    {
        return in_array($rule, static::PROXIED_RULES);
    }

    protected function applyLocalRule($rule, $arguments = [])
    {
        if ($this->isCustomRule($rule)) {
            return call_user_func_array([$this, $this->customRuleMethod($rule)], $arguments);
        }

        if (!empty($arguments)) {
            $rule .= ':'.implode(',', Arr::flatten($arguments));
        }

        $this->localRules[] = $rule;

        return $this;
    }

    protected function applyProxyRule($method, $arguments)
    {
        $this->proxiedRules[] = call_user_func_array([LaravelRule::class, $method], $arguments);

        return $this;
    }

    protected function isCustomRule($rule)
    {
        return method_exists($this, $this->customRuleMethod($rule));
    }

    protected function customRuleMethod($rule)
    {
        return Str::camel($rule).'Rule';
    }

    protected function canApplyToLatestProxyRule($method)
    {
        $proxy = Arr::last($this->proxiedRules);

        return !is_null($proxy) && method_exists($proxy, $method);
    }

    protected function applyToLatestProxyRule($method, $arguments)
    {
        $proxy = Arr::last($this->proxiedRules);

        call_user_func_array([$proxy, $method], $arguments);

        return $this;
    }

    protected function allRules()
    {
        return array_merge($this->localRules, $this->proxiedRules);
    }

    protected static function whenRule($condition, callable $callback)
    {
        $shouldCall = is_callable($condition) ? call_user_func($condition) : $condition;

        if ($shouldCall) {
            call_user_func($callback, $this);
        }

        return $this;
    }

    public function get()
    {
        return $this->allRules();
    }

    public function __toString()
    {
        return implode('|', $this->allRules());
    }

    // custom rules

    protected function emailRule($max = null)
    {
        $this->localRules[] = 'email';

        if (!is_null($max)) {
            $this->max($max);
        }

        return $this;
    }

    protected function activeUrlRule($max = null)
    {
        $this->localRules[] = 'active_url';

        if (!is_null($max)) {
            $this->max($max);
        }

        return $this;
    }

    protected function characterRule()
    {
        return $this->alpha()->max(1);
    }

    protected function alphaRule()
    {
        $this->localRules[] = 'alpha';

        return $this->applyMinAndMaxFromFunctionArguments(func_get_args());
    }

    protected function alphaDashRule()
    {
        $this->localRules[] = 'alpha_dash';

        return $this->applyMinAndMaxFromFunctionArguments(func_get_args());
    }

    protected function alphaNumRule()
    {
        $this->localRules[] = 'alpha_num';

        return $this->applyMinAndMaxFromFunctionArguments(func_get_args());
    }

    protected function fileRule($max = null)
    {
        $this->localRules[] = 'file';

        if (!is_null($max)) {
            $this->size($max);
        }

        return $this;
    }

    protected function imageRule($max = null)
    {
        $this->localRules[] = 'image';

        if (!is_null($max)) {
            $this->size($max);
        }

        return $this;
    }

    protected function json($max = null)
    {
        $this->localRules[] = 'json';

        if (!is_null($max)) {
            $this->size($max);
        }

        return $this;
    }

    protected function url($max = null)
    {
        $this->localRules[] = 'url';

        if (!is_null($max)) {
            $this->size($max);
        }

        return $this;
    }

    protected function stringRule()
    {
        $this->localRules[] = 'string';

        return $this->applyMinAndMaxFromFunctionArguments(func_get_args());
    }

    protected function integerRule()
    {
        $this->localRules[] = 'integer';

        return $this->applyMinAndMaxFromFunctionArguments(func_get_args());
    }

    protected function numericRule()
    {
        $this->localRules[] = 'numeric';

        return $this->applyMinAndMaxFromFunctionArguments(func_get_args());
    }

    protected function applyMinAndMaxFromFunctionArguments($arguments)
    {
        if (empty($arguments)) {
            return $this;
        }

        if (is_array($arguments[0])) {
            $arguments = $arguments[0];
        }

        list($min, $max) = array_merge($arguments, [null, null]);

        if (!is_null($min)) {
            $this->min($min);
        }

        if (!is_null($max)) {
            $this->max($max);
        }

        return $this;
    }
}

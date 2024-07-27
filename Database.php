<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    const PATTERNS = [
        '?d' => 'int',
        '?f' => 'float',
        '?a' => 'array',
        '?#' => 'identificator',
        '?' => 'any',
    ];
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function __call($name, $arguments)
    {
        if (str_starts_with($name, 'make') && !method_exists($this, $name)) {
            throw new Exception('Call to undefined method ' . __CLASS__ . '::' . $name . '()');
        }
    }

    protected function makeInt($value): int
    {
        return (int) $value;
    }

    protected function makeString($value): string
    {
        return "'" . (string) $value . "'";
    }

    protected function makeBool($value): bool
    {
        return (bool) $value;
    }

    protected function makeFloat($value): float
    {
        return (float) $value;
    }
    protected function makeAny($value)
    {
        switch (gettype($value)) {
            case 'integer':
                return $this->makeInt($value);
            case 'float':
                return $this->makeFloat($value);
            case 'boolean':
                return $this->makeBool($value);
            case 'string':
                return $this->makeString($value);
            case 'NULL':
                return 'NULL';
            default:
                throw new Exception("Unsupported type '" . gettype($value) . "'");
        }
    }

    protected function makeIdentificator($value): string
    {
        if (is_array($value)) {
            return "`" . implode('`, `', $value) . "`";
        } else {
            return "`" . $value . "`";
        }
    }

    protected function makeArray($value): string
    {
        if (!is_array($value)) {
            throw new Exception("Unsupported type '" . gettype($value) . "'. Need array");
        }

        $ret = [];
        foreach ($value as $k => $v) {
            $ret[] = is_numeric($k) ?  $this->makeAny($v) : ($this->makeIdentificator($k) . " = " . $this->makeAny($v));
        }

        return implode(', ', $ret);
    }
    public function buildQuery(string $query, array $args = []): string
    {
        $currentArg = 0;
        $patterns = array_map(fn ($item) => "\\$item", array_keys(self::PATTERNS));
        $ret = preg_replace_callback('/' . implode('|', $patterns) . '/', function ($matches) use (&$currentArg, $args) {
            $f = 'make' . ucfirst(self::PATTERNS[$matches[0]]);
            return $this->$f($args[$currentArg++]);
        }, $query);

        $ret = preg_replace('/\{.+=\s' . $this->skip() . '.*\}/m', '', $ret);
        $ret = preg_replace('/\{|\}/', '', $ret);

        return $ret;
    }

    public function skip()
    {
        return 645;
    }
}

<?php

namespace CarlosMora\QueryContext\Enums;

enum FilterOperator: string
{
    case Eq         = 'eq';
    case Gt         = 'gt';
    case Lt         = 'lt';
    case Ge         = 'ge';
    case Le         = 'le';
    case In         = 'in';
    case Nin        = 'nin';
    case IsNull     = 'isNull';
    case Like       = 'like';
    case Rlike      = 'rlike';
    case StartsWith = 'startsWith';
    case Llike      = 'llike';
    case EndsWith   = 'endsWith';

    public function toSqlOperator(): string
    {
        return match ($this) {
            self::Gt => '>',
            self::Lt => '<',
            self::Ge => '>=',
            self::Le => '<=',
        };
    }

    public function isComparison(): bool
    {
        return in_array($this, [self::Gt, self::Lt, self::Ge, self::Le], true);
    }
}

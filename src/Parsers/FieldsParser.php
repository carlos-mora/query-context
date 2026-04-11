<?php

namespace CarlosMora\QueryContext\Parsers;

class FieldsParser
{
    /**
     * Parse a fields array into SELECT columns, eager-load relations and withCount relations.
     *
     * Token rules (each array element is a single token):
     *  - `count(relation)`      → withCount entry
     *  - `relation:col1,col2`   → with entry (colon-notation, columns must not contain spaces)
     *  - `relation:*`           → with entry (all columns)
     *  - `relation.column`      → with entry (dot-notation, grouped by relation name)
     *  - plain string           → SELECT column on the main model
     *
     * @param  string[]|null  $fields
     * @return array{select: string[]|null, with: string[], withCount: string[]}
     */
    public static function parse(?array $fields): array
    {
        if (empty($fields)) {
            return ['select' => null, 'with' => [], 'withCount' => []];
        }

        $select    = [];
        $dotGroups = [];
        $with      = [];
        $withCount = [];

        foreach ($fields as $token) {
            $token = trim((string) $token);

            if ($token === '') {
                continue;
            }

            if (preg_match('/^count\((.+)\)$/i', $token, $matches)) {
                $withCount[] = trim($matches[1]);
            } elseif (str_contains($token, ':')) {
                [$relation, $cols] = explode(':', $token, 2);
                $relation = trim($relation);

                if ($relation === '') {
                    continue;
                }

                $cols   = trim($cols);
                $with[] = ($cols === '' || $cols === '*') ? $relation : $relation.':'.$cols;
            } elseif (str_contains($token, '.')) {
                [$relation, $column] = explode('.', $token, 2);
                $relation = trim($relation);
                $column   = trim($column);

                if ($relation !== '' && $column !== '') {
                    $dotGroups[$relation][] = $column;
                }
            } else {
                $select[] = $token;
            }
        }

        foreach ($dotGroups as $relation => $columns) {
            $with[] = $relation.':'.implode(',', $columns);
        }

        return [
            'select'    => empty($select) ? null : $select,
            'with'      => $with,
            'withCount' => $withCount,
        ];
    }
}

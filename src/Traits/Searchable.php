<?php

namespace Searchable\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * Returns an array of columns that are searchable by the Searchable trait.
     *
     * @return array Array of searchable columns. Each key is the column name and
     *     the value is either a string (the label to use for the column in the
     *     search form) or an array with 'label' as the label and 'visible' as a
     *     boolean indicating whether to show the column in the search form.
     */
    public static function getSearchableColumns(): array
    {
        return static::$searchable ?? [];
    }

    /**
     * Scope a query to only include models matching the search term.
     *
     * If the $column parameter is set to 'all', the query will search all
     * columns that are searchable according to the $searchable class property.
     * Otherwise, it will only search the column with the given name.
     *
     * @param Builder $query The query builder.
     * @param string|null $term The search term.
     * @param string $column The column to search. Defaults to 'all'.
     * @return Builder
     */
    public function scopeSearch(Builder $query, ?string $term = null, string $column = 'all'): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term, $column) {
            $searchable = static::getSearchableColumns();

            if ($column === 'all') {
                foreach ($searchable as $col => $config) {
                    $this->applySearchCondition($q, $col, $config, $term);
                }
            } elseif (isset($searchable[$column])) {
                $this->applySearchCondition($q, $column, $searchable[$column], $term);
            }
        });
    }

    /**
     * Apply a search condition for a searchable column.
     *
     * @param Builder $query The query builder.
     * @param string $column The name of the column.
     * @param mixed $config The configuration for the column, either a string label or an array.
     * @param string $term The search term.
     * @return void
     */
    protected function applySearchCondition(Builder $query, string $column, mixed $config, string $term): void
    {
        $config = $this->normalizeConfig($column, $config);

        if ($this->isRelationSearch($config)) {
            $this->applyRelationSearch($query, $config, $term);
        } else {
            $this->applyDirectSearch($query, $config, $term);
        }
    }

    /**
     * Normalize the configuration for a searchable column.
     *
     * @param string $column The name of the column.
     * @param mixed $config The configuration for the column, either a string label or an array.
     * @return array The normalized configuration array with at least a 'field' key.
     */
    protected function normalizeConfig(string $column, mixed $config): array
    {
        if (!is_array($config)) {
            return ['field' => $column, 'label' => $config];
        }

        return array_merge(['field' => $column], $config);
    }

    /**
     * Check if the given configuration is for a relation search.
     *
     * @param array $config The configuration for the searchable column.
     * @return bool True if the configuration is for a relation search, false otherwise.
     */
    protected function isRelationSearch(array $config): bool
    {
        return isset($config['relation'], $config['field']);
    }

    /**
     * Apply a search condition for a relation search.
     *
     * @param Builder $query The query builder.
     * @param array $config The configuration for the searchable column.
     * @param string $term The search term.
     * @return void
     */
    protected function applyRelationSearch(Builder $query, array $config, string $term): void
    {
        $query->orWhereHas($config['relation'], function (Builder $q) use ($config, $term) {
            $field = $config['field'];

            // Convert binary to utf8 for comparison
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $term)) {
                $q->whereRaw("CONVERT(`{$field}` USING utf8mb4) LIKE ?", ['%' . $term . '%']);
            } else {
                $this->applyDirectSearch($q, $config, $term);
            }
        });
    }

    /**
     * Apply a search condition for a direct search.
     *
     * @param Builder $query The query builder.
     * @param array $config The configuration for the searchable column.
     * @param string $term The search term.
     * @return void
     */
    protected function applyDirectSearch(Builder $query, array $config, string $term): void
    {
        $field = $config['field'];
        $isNumeric = $this->shouldUseNumericSearch($config, $term);

        if ($isNumeric && is_numeric($term)) {
            $numericValue = (float)$term;

            $query->orWhere(function (Builder $q) use ($field, $numericValue) {
                $q->where($field, '=', $numericValue)
                    ->orWhereRaw("ABS(ROUND(CAST($field AS DECIMAL(15,4)), 0) - ?) < 0.0001", [$numericValue]);
            });
        } else {
            $query->orWhere($field, 'LIKE', "%{$term}%");
        }
    }

    /**
     * Determine if the given search term should be compared numerically.
     *
     * This method first checks if a type is explicitly set in the configuration.
     * If not, it checks if the field name suggests a numeric type based on a list
     * of common patterns. If the field name doesn't match any of the patterns, it
     * falls back to checking if the term is numeric.
     *
     * @param array $config The configuration for the searchable column.
     * @param string $term The search term.
     * @return bool True if the term should be compared numerically, false otherwise.
     */
    protected function shouldUseNumericSearch(array $config, string $term): bool
    {
        // Explicit type declaration takes priority
        if (isset($config['type'])) {
            return $config['type'] === 'number';
        }

        // Check if field name suggests numeric type
        $numericPatterns = ['amount', 'total', 'price', 'cost', 'quantity', 'number'];
        $field = strtolower($config['field']);

        foreach ($numericPatterns as $pattern) {
            if (str_contains($field, $pattern)) {
                return true;
            }
        }

        return is_numeric($term);
    }

    /**
     * Get the options for searchable columns, with the column name as key and
     * the label as value.
     *
     * @return array The options for searchable columns.
     */
    public static function getSearchColumnOptions(): array
    {
        $options = [];

        foreach (static::getSearchableColumns() as $column => $config) {
            $options[$column] = is_array($config)
                ? ($config['label'] ?? ucfirst(str_replace('_', ' ', $column)))
                : $config;
        }

        return $options;
    }
}

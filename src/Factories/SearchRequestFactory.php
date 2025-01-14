<?php declare(strict_types=1);

namespace ElasticScoutDriver\Factories;

use ElasticAdapter\Search\SearchRequest;
use Laravel\Scout\Builder;
use stdClass;

class SearchRequestFactory implements SearchRequestFactoryInterface
{
    public function makeFromBuilder(Builder $builder, array $options = []): SearchRequest
    {
        $searchRequest = new SearchRequest($this->makeQuery($builder));

        if ($sort = $this->makeSort($builder)) {
            $searchRequest->sort($sort);
        }

        if ($from = $this->makeFrom($options)) {
            $searchRequest->from($from);
        }

        if ($size = $this->makeSize($builder, $options)) {
            $searchRequest->size($size);
        }

        return $searchRequest;
    }

    protected function makeQuery(Builder $builder): array
    {
        $query = [
            'bool' => [],
        ];

        if (!empty($builder->query)) {
            $query['bool']['must'] = [
                'query_string' => [
                    'query' => $builder->query,
                ],
            ];
        } else {
            $query['bool']['must'] = [
                'match_all' => new stdClass(),
            ];
        }

        if ($filter = $this->makeFilter($builder)) {
            $query['bool']['filter'] = $filter;
        }

        return $query;
    }

    protected function makeFilter(Builder $builder): ?array
    {
        $wheres = collect($builder->wheres)->map(static function ($value, string $field) {
            return [
                'term' => [$field => $value],
            ];
        })->values();

        $whereIns = collect($builder->whereIns ?? [])->map(static function (array $values, string $field) {
            return [
                'terms' => [$field => $values],
            ];
        })->values();

        $filter = $wheres->merge($whereIns);

        return $filter->isEmpty() ? null : $filter->all();
    }

    protected function makeSort(Builder $builder): ?array
    {
        $sort = collect($builder->orders)->map(static function (array $order) {
            return [
                $order['column'] => $order['direction'],
            ];
        });

        return $sort->isEmpty() ? null : $sort->all();
    }

    protected function makeFrom(array $options): ?int
    {
        if (isset($options['page']) && isset($options['perPage'])) {
            return ($options['page'] - 1) * $options['perPage'];
        }

        return null;
    }

    protected function makeSize(Builder $builder, array $options): ?int
    {
        return $options['perPage'] ?? $builder->limit;
    }
}

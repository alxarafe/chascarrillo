<?php

namespace Modules\Chascarrillo\Lib\Filter;

use Alxarafe\Component\AbstractFilter;

class PostStatusFilter extends AbstractFilter
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @param mixed $value
     * @return void
     */
    #[\Override]
    public function apply($query, $value): void
    {
        switch ($value) {
            case 'published':
                $query->where('is_published', true)
                    ->where('published_at', '<=', date('Y-m-d H:i:s'));
                break;
            case 'draft':
                $query->where('is_published', false);
                break;
            case 'scheduled':
                $query->where('is_published', true)
                    ->where('published_at', '>', date('Y-m-d H:i:s'));
                break;
        }
    }

    #[\Override]
    public function getType(): string
    {
        return 'select';
    }
}

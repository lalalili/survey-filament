<?php

namespace Lalalili\SurveyFilament\Support;

use Illuminate\Database\Eloquent\Builder;

class SurveyQueryScopes
{
    /**
     * Apply the host application's tenant scope to a Survey query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function surveys(Builder $query): Builder
    {
        $scope = config('survey-filament.query_scope');

        if (is_callable($scope)) {
            $query = $scope($query, auth()->user());
        }

        return $query;
    }

    /**
     * Apply the host application's tenant scopes to a SurveyResponse query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function responses(Builder $query): Builder
    {
        $scope = config('survey-filament.query_scope');

        if (is_callable($scope)) {
            // Scope through the survey relationship so tenant isolation propagates.
            $query->whereHas('survey', fn (Builder $q) => $scope($q, auth()->user()));
        }

        $responseScope = config('survey-filament.response_query_scope');

        if (is_callable($responseScope)) {
            // Applied directly to the response query so it can filter by recipient payload.
            $query = $responseScope($query, auth()->user());
        }

        return $query;
    }
}

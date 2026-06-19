<?php

namespace App\Repositories;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class BaseRepository
{
    public function __construct(protected Model $model)
    {
    }

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->all($columns);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->paginate($perPage, $columns);
    }

    public function find(int|string $id): ?Model
    {
        return $this->model->find($id);
    }

    public function findOrFail(int|string $id): Model
    {
        return $this->model->findOrFail($id);
    }

    public function findBy(string $field, mixed $value): ?Model
    {
        return $this->model->where($field, $value)->first();
    }

    public function findWhere(array $conditions): Collection
    {
        return $this->model->where($conditions)->get();
    }

    public function first(array $conditions = []): ?Model
    {
        return $this->model->where($conditions)->first();
    }

    public function firstOrFail(array $conditions = []): Model
    {
        return $this->model->where($conditions)->firstOrFail();
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->model->updateOrCreate($attributes, $values);
    }

    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    public function count(array $conditions = []): int
    {
        return $this->model->where($conditions)->count();
    }

    public function exists(array $conditions): bool
    {
        return $this->model->where($conditions)->exists();
    }

    public function with(array|string $relations): Builder
    {
        return $this->model->with($relations);
    }

    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function paginateWhere(array $conditions, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where($conditions)->paginate($perPage);
    }

    public function orderBy(string $column, string $direction = 'asc'): Builder
    {
        return $this->model->orderBy($column, $direction);
    }
}

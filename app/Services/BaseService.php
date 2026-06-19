<?php

namespace App\Services;

use App\Repositories\BaseRepository;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseService
{
    public function __construct(protected BaseRepository $repository)
    {
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->repository->all($columns);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $columns);
    }

    public function find(int|string $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function findOrFail(int|string $id): Model
    {
        return $this->repository->findOrFail($id);
    }

    public function findBy(string $field, mixed $value): ?Model
    {
        return $this->repository->findBy($field, $value);
    }

    public function findWhere(array $conditions): Collection
    {
        return $this->repository->findWhere($conditions);
    }

    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->repository->updateOrCreate($attributes, $values);
    }

    public function update(Model $model, array $data): bool
    {
        return $this->repository->update($model, $data);
    }

    public function delete(Model $model): bool
    {
        return $this->repository->delete($model);
    }

    public function count(array $conditions = []): int
    {
        return $this->repository->count($conditions);
    }

    public function exists(array $conditions): bool
    {
        return $this->repository->exists($conditions);
    }

    public function transaction(Closure $callback): mixed
    {
        return $this->repository->transaction($callback);
    }
}

<?php

/**
 * Copyright 2017 Cloud Creativity Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace CloudCreativity\LaravelJsonApi\Http\Controllers;

use Closure;
use CloudCreativity\JsonApi\Contracts\Http\Requests\RequestInterface;
use CloudCreativity\JsonApi\Contracts\Hydrator\HydratorInterface;
use CloudCreativity\JsonApi\Contracts\Object\ResourceObjectInterface;
use CloudCreativity\JsonApi\Contracts\Store\StoreInterface;
use CloudCreativity\JsonApi\Exceptions\RuntimeException;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Class JsonApiController
 *
 * @package CloudCreativity\LaravelJsonApi
 */
abstract class JsonApiController extends Controller
{

    use CreatesResponses;

    /**
     * The hydrator fully-qualified class name, or service name.
     *
     * @var HydratorInterface|string|null
     */
    protected $hydrator;

    /**
     * The database connection name to use for transactions, or null for the default connection.
     *
     * @var string|null
     */
    protected $connection;

    /**
     * Whether database transaction should be used.
     *
     * @var bool
     */
    protected $useTransactions = true;

    /**
     * @param $record
     * @return bool
     *      whether the record was successfully deleted.
     */
    abstract protected function destroyRecord($record);

    /**
     * @param StoreInterface $store
     * @param RequestInterface $request
     * @return Response
     */
    public function index(StoreInterface $store, RequestInterface $request)
    {
        return $this->reply()->content(
            $this->doSearch($store, $request)
        );
    }

    /**
     * @param object $record
     * @return Response
     */
    public function read($record)
    {
        return $this->reply()->content($record);
    }

    /**
     * @param ResourceObjectInterface $resource
     * @return Response
     */
    public function create(ResourceObjectInterface $resource)
    {
        $record = $this->transaction(function () use ($resource) {
            return $this->doCreate($resource);
        });

        return $this->reply()->created($record);
    }

    /**
     * @param ResourceObjectInterface $resource
     * @param object $record
     * @return Response
     */
    public function update(ResourceObjectInterface $resource, $record)
    {
        $record = $this->transaction(function () use ($resource, $record) {
            return $this->doUpdate($resource, $record);
        });

        return $this->reply()->content($record);
    }

    /**
     * @param $record
     * @return Response
     */
    public function delete($record)
    {
        $this->transaction(function () use ($record) {
            $this->doDelete($record);
        });

        return $this->reply()->noContent();
    }

    /**
     * @param StoreInterface $store
     * @param RequestInterface $request
     * @return mixed
     */
    protected function doSearch(StoreInterface $store, RequestInterface $request)
    {
        return $store->query($request->getResourceType(), $request->getParameters());
    }

    /**
     * @param ResourceObjectInterface $resource
     * @return object
     */
    protected function doCreate(ResourceObjectInterface $resource)
    {
        if (method_exists($this, 'creating')) {
            $this->creating($resource);
        }

        $record = $this->hydrator()->create($resource);

        if (method_exists($this, 'created')) {
            $this->created($resource, $record);
        }

        return $record;
    }

    /**
     * @param ResourceObjectInterface $resource
     * @param $record
     * @return object
     */
    protected function doUpdate(ResourceObjectInterface $resource, $record)
    {
        if (method_exists($this, 'updating')) {
            $this->updating($resource, $record);
        }

        $record = $this->hydrator()->update($resource, $record);

        if (method_exists($this, 'updated')) {
            $this->updated($resource, $record);
        }

        return $record;
    }

    /**
     * @param $record
     * @return void
     */
    protected function doDelete($record)
    {
        if (method_exists($this, 'deleting')) {
            $this->deleting($record);
        }

        if (!$this->destroyRecord($record)) {
            throw new RuntimeException('Record was not successfully deleted.');
        }

        if (method_exists($this, 'deleted')) {
            $this->deleted($record);
        }
    }

    /**
     * @param Closure $closure
     * @return mixed
     */
    protected function transaction(Closure $closure)
    {
        if (!$this->useTransactions) {
            return $closure();
        }

        return app('db')->connection($this->connection)->transaction($closure);
    }

    /**
     * @return HydratorInterface
     */
    protected function hydrator()
    {
        if ($this->hydrator instanceof HydratorInterface) {
            return $this->hydrator;
        }

        if (!$this->hydrator) {
            throw new RuntimeException('The hydrator property must be set.');
        }

        $hydrator = app($this->hydrator);

        if (!$hydrator instanceof HydratorInterface) {
            throw new RuntimeException("Service $this->hydrator is not a hydrator.");
        }

        return $hydrator;
    }

}

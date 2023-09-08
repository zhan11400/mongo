<?php
/**
 * Create by
 * User: 湛工
 * DateTime: 2020/6/18 15:21
 * Email:  1140099248@qq.com
 */

namespace since;



use MongoDB\BSON\ObjectId;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\Exception;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

class MongoDB
{
    /**
     * @var mixed
     */
    private $database;
    /**
     * @var
     */
    private $collection;
    /**
     * @var Manager
     */
    private $manager;
    /**
     * @var Query
     */
    private $query = [];
    /**
     * @var string[]  查询表达式
     */
    protected $exp = ['<>' => 'ne', '=' => 'eq', '>' => 'gt', '>=' => 'gte', '<' => 'lt', '<=' => 'lte', 'in' => 'in', 'not in' => 'nin', 'nin' => 'nin', 'mod' => 'mod', 'exists' => 'exists', 'null' => 'null', 'notnull' => 'not null', 'not null' => 'not null', 'regex' => 'regex', 'type' => 'type', 'all' => 'all', '> time' => '> time', '< time' => '< time', 'between' => 'between', 'not between' => 'not between', 'between time' => 'between time', 'not between time' => 'not between time', 'notbetween time' => 'not between time', 'like' => 'like', 'near' => 'near', 'size' => 'size'];

    /**
     * @var array
     */
    protected $config = [
        'host' => '127.0.0.1',
        "port" => 27017,
        "username" => "",
        "password" => "",
        "database" => "test"
    ];
    /**
     * @var \array[][]
     */
    private $pipeline = [];
    /**
     * @var float|int
     */
    private $skip;
    private $limit;
    private $sort = [];
    private $projection=[];

    /**
     * MongoDB constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
        $uri = sprintf("mongodb://%s%s%s:%s/%s", $this->config["username"], !empty($this->config["password"]) ? ":" . $this->config["password"] . "@" : "", $this->config["host"], $this->config["port"], $this->config["database"]);
        $this->database = $this->config["database"];
        $this->manager = new Manager($uri, []);
        // echo "<pre>";
        return $this;
    }

    /**
     * 2023-9-1
     * @param string $name
     * @param null $default
     * @return array|mixed|null
     */
    public function getConfig(string $name = '', $default = null)
    {
        if ('' !== $name) {
            return $this->config[$name] ?? $default;
        }
        return $this->config;
    }

    /** 设置要切换的数据库
     * 2023-9-1
     * @param $db
     * @return $this
     */
    public function db($db): MongoDB
    {
        $this->database = $db;
        return $this;
    }

    /**要查询的集合(MySQL的表)
     * 2023-9-1
     * @param $table
     * @return $this
     */
    public function table($table): MongoDB
    {
        $this->collection = $table;
        return $this;
    }

    /**筛选的条件
     * 2023-9-1
     * @param array $filter 格式 ["age","=","18]
     * @return $this
     * @throws \Exception
     */
    public function where(array $filters): MongoDB
    {
        $query = $this->filter($filters);
        $this->query = $query;
        return $this;
    }

    /** 处理filter
     * 2023-9-8
     * @param $filters
     * @return array|array[]|\array[][][]
     * @throws \Exception
     */
    private function filter($filters)
    {
        $query = [];
        foreach ($filters as $filter) {
            if (!is_array($filter) || count($filter) != 3) {
                throw new   \Exception("where参数格式错误");
            }
            list($key, $operator, $value) = $filter;
            if (!in_array($operator, array_keys($this->exp))) {
                throw new   \Exception("$operator 非法字符");
            }
            if ($operator == 'between') {
                $query[$key] = ['$gte' => $value[0], '$lte' => $value[1]];
            } elseif ($operator == 'not between') {
                $query = array(
                    '$or' => array(
                        array($key => array('$lt' => $value[0])),
                        array($key => array('$gt' => $value[1]))
                    )
                );
            } elseif ($operator == 'like') {
                $query = array($key => array('$regex' => $value));
            } else {
                $query[$key] = ["$" . $this->exp[$operator] => $value];
            }
            // 设置查询条件
            if ($key == "_id") {
                $query["_id"] = new ObjectId($value);
            }
        }
        return $query;
    }

    /**用 or
     * 2023-9-8
     * @param array $filters
     * @return $this
     * @throws \Exception
     */
    public function orWhere(array $filters): MongoDB
    {
        $query = $this->filter($filters);
        $this->query = array(
            '$or' => array(
                $this->query,
                $query
            )
        );
        return $this;
    }

    /**分页
     * 2023-9-1
     * @param $page
     * @param $pageSize
     * @return $this
     */
    public function page($page, $pageSize): MongoDB
    {
        $page = $page - 1 <= 0 ? 0 : $page - 1;
        $this->skip = $page * $pageSize;
        $this->limit = $pageSize;
        return $this;
    }

    /** 查询数量
     * 2023-9-8
     * @param $pageSize
     * @return $this
     */
    public function limit($pageSize): MongoDB
    {
        $this->limit = $pageSize;
        return $this;
    }
    /**查询
     * 2023-9-1
     * @return array
     * @throws Exception
     */
    /*  public function select(): array
      {
          // 执行查询
          $cursor = $this->manager->executeQuery("$this->database.$this->collection", $this->query);

          return   $cursor->toArray();
      }*/


    /**插入
     * 2023-9-1
     * @param array $document
     * @param false $multi false插入一条，true插入多条
     * @return int|null
     */
    public function insert(array $document, bool $multi = false): ?int
    {
        $bulkWrite = new BulkWrite();
        if ($multi) {
            // 添加插入操作
            foreach ($document as $doc) {
                $bulkWrite->insert($doc);
            }
        } else {
            $bulkWrite->insert($document);
        }
        return $this->manager->executeBulkWrite($this->database . '.' . $this->collection, $bulkWrite)->getInsertedCount();
    }

    /**
     * 2023-9-1
     * @param array $documents 需要二维数组
     * @return int|null
     */
    public function insertAll(array $documents): ?int
    {
        $bulkWrite = new BulkWrite();
        // 添加插入操作
        foreach ($documents as $doc) {
            $bulkWrite->insert($doc);
        }
        return $this->manager->executeBulkWrite($this->database . '.' . $this->collection, $bulkWrite)->getInsertedCount();
    }

    /** 更新数据，允许更新多条
     * 2023-9-1
     * @param $document
     * @return int
     */
    public function update($document): int
    {
        $bulkWrite = new BulkWrite();
        $update = ['$set' => $document];
        $bulkWrite->update($this->query, $update, ["multi" => true, "upsert" => false, "writeConcern" => false]);
        return $this->manager->executeBulkWrite($this->database . '.' . $this->collection, $bulkWrite)->getModifiedCount();
    }

    /**
     * 2023-9-1
     * @param string|array $field
     * @param int $value
     * @return int
     */
    public function inc($field, int $value = 1): int
    {
        $bulkWrite = new BulkWrite();
        if (is_string($field)) {
            $update = ['$inc' => [$field => $value]];
        } elseif (is_array($field)) {
            foreach ($field as $k => $v) {
                $field[$k] = intval($v);
            }
            $update = ['$inc' => $field];
        } else {
            return 0;
        }
        $bulkWrite->update($this->query, $update, ["multi" => true, "upsert" => false, "writeConcern" => false]);
        return $this->manager->executeBulkWrite($this->database . '.' . $this->collection, $bulkWrite)->getModifiedCount();
    }

    /**
     * 2023-9-1  mongoDB没有decrement指令，需要通过inc实现
     * @param string|array $field
     * @param int $value
     * @return int
     */
    public function dec($field, int $value = 1): int
    {
        if (is_string($field)) {
            return $this->inc($field, -$value);
        } elseif (is_array($field)) {
            foreach ($field as $k => $v) {
                $field[$k] = intval(-$v);
            }
            return $this->inc($field);
        }
        return 0;
    }

    /**删除数据
     * 2023-9-1
     * @return int|null
     */
    public function delete(): ?int
    {
        $bulkWrite = new BulkWrite();
        // 添加删除操作
        $bulkWrite->delete($this->query);
        return $this->manager->executeBulkWrite($this->database . '.' . $this->collection, $bulkWrite)->getDeletedCount();
    }

    //$group accumulator操作符
    //$avg	计算均值	avg
    //$first	返回每组第一个文档，如果有排序，按照排序，如果没有按照默认的存储的顺序的第一个文档。
    //$last	返回每组最后一个文档，如果有排序，按照排序，如果没有按照默认的存储的顺序的最后个文档。
    //$max	根据分组，获取集合中所有文档对应值得最大值。
    //$min	根据分组，获取集合中所有文档对应值得最小值。
    //$push	将指定的表达式的值添加到一个数组中。
    //$addToSet	将表达式的值添加到一个集合中（无重复值，无序）。
    //$sum	计算总和	sum
    //$stdDevPop	返回输入值的总体标准偏差（population standard deviation）
    //$stdDevSamp	返回输入值的样本标准偏差（the sample standard deviation）
    /**
     * 2023-9-8
     * @param string $field 要分组的字段
     * @param array $data 其他要操作的字段与对应的值
     * @return $this
     */
    public function group(string $field, array $data)
    {
        $group = ['_id' => '$' . $field];
        $keys = array_keys($data);
        $project = [];
        foreach ($keys as $v) {
            $project[$v] = 1;
        }
        $project[$field] ='$_id';
        $project["_id"] =0;
        // 构建聚合管道
        array_push($this->pipeline, ['$group' => array_merge($group, $data)], ['$project' => $project]);
        return $this;
    }

    /**
     * 2023-9-8
     * @param array $data 自定义要输出的字符与对应的值
     * @return $this
     */
    public function project(array $data)
    {
        // 构建聚合管道
        array_push($this->pipeline, [
            '$project' => $data
        ]);
        return $this;
    }

    /**对分组的进行条件筛选
     * 2023-9-8
     * @param array $data
     * @return $this
     */
    public function having(array $data)
    {

        array_push($this->pipeline, [
            '$match' => $data
        ]);
        return $this;
    }

    /** 排序
     * 2023-9-8
     * @param array $sort
     * @return $this
     */
    public function sort(array $sort): MongoDB
    {
        foreach ($sort as $k => $v) {
            $this->sort[$k] = ($v == 'asc' ? 1 : -1);
        }
        return $this;
    }

    /** 排序
     * 2023-9-8
     * @param string $order
     * @return $this
     */
    public function order(string $order): MongoDB
    {
        $sort=explode(",",$order);
        foreach ($sort as $v) {
           list($key,$value)=explode(" ",$v);
            $this->sort[$key] = (trim($value) == 'asc' ? 1 : -1);
        }
        return $this;
    }

    public function field(string $field): MongoDB
    {
        $data=explode(",",$field);
        foreach ($data as $v) {
            $this->projection[$v] =1;
        }
        return $this;
    }
    /**
     * 2023-9-8
     * @return mixed
     * @throws Exception
     */
    public function find()
    {
        return $this->select()[0];
    }

    /**
     * 2023-9-8
     * @return array
     * @throws Exception
     */
    public function select()
    {
        if ($this->pipeline) {
            $commandParam['aggregate'] = $this->collection;
            $commandParam['pipeline'] = $this->pipeline;
            $commandParam['cursor'] = new \stdClass();
        } else {
            $commandParam['find'] = $this->collection;
            if ($this->limit) $commandParam['limit'] = $this->limit;
            if ($this->skip) $commandParam['skip'] = $this->skip;
            if ($this->query) $commandParam['filter'] = $this->query;
            if ($this->sort) $commandParam['sort'] = $this->sort;
            if ($this->projection) $commandParam['projection'] = $this->projection;

        }

        $query = new Command($commandParam);
        $this->limit = $this->skip = $this->query = null;
        $this->pipeline = $this->sort = [];
        $result=$this->manager->executeCommand("$this->database", $query)->toArray();
        foreach ($result as $key =>&$value) {
            $data=(array)$value;
            if(isset($data["_id"])){
                $data["_id"] = (string)$data["_id"];
            }
            $result[$key] = $data;
        }
        return  $result;
    }

    /** 统计文档数量
     * 2023-9-8
     * @return mixed
     * @throws Exception
     */
    public function count()
    {
        $commandParam['count'] = $this->collection;
        $commandParam['query'] = $this->query;
        $query = new Command($commandParam);
        $result = $this->manager->executeCommand("$this->database", $query)->toArray();
        $resultDocument = current($result);
        return $resultDocument->n; // 获取结果中的文档数量
    }
}

#### mongoDB
仿照thinkphp6的方式来封装使用mongoDB
>table 设置要操作的集合，对于MySQL来说就是表
where 设置筛选条件，参数必须是二维数据
field  要查询的字段，多字段时用英文逗号分隔
limit 查询数量
page 翻页
select 返回二维数组
find 返回一维数组
group 分组
having 筛选分组，用group时才有效果
project 要输出的字段，用group时才有效果
count  返回数量
dec 减少，数字才能用
inc 增加，数字才能用
sort 排序，数组形式
order 也是排序，字符串形式
insert  插入数据一条或多条
insertAll 查询多条数据

####安装
```cmd
composer require since/mongo
```
####使用示例
```php
use since\mongo;
```
1. 初始化
```phpt
  $mongo=new MongoDB();
 //可以自定义参数
  $config = [
        'host' => '127.0.0.1',
        "port" => 27017,
        "username" => "",
        "password" => "",
        "database" => "test"
    ]
    $mongo=new MongoDB($config);
```
2. 增
```
  //新增一条
 $document = array(
                "name" => "John",
                "age" => 16,
                "sex" => 1
            );
  $mongo->table("user")->insert($document);
 //新增多条
            $document = [
                array(
                    "name" => "john",
                    "age" => 16,
                    "sex" => 1
                ),array(
                    "name" => "tom",
                    "age" => 18,
                    "sex" => 1
                ),array(
                    "name" => "lucy",
                    "age" => 18,
                    "sex" => 2
                )];
        $res=$mod->table("user")->insertAll($document);
   //或者     
   $res=$mod->table("user")->insert($document,true);
```
3. 删
```
//整个表删除数据
$where=[
                ["name","=","john"],
            ];
            //where参数是二维数组
          $res=$mongo->table("user")->where($where)->delete();
```
4. 改
```
           $where=[
                ["name","=","tom"],
            ];
            //直接修改年龄
            $res=$mongo->table("user")->where($where)->update(["age"=>20]);
            $where=[
                ["name","=","lucy"],
            ];
            //年龄+1
            $res=$mongo->table("user")->where($where)->inc("age");
            //年龄+5
            $res=$mongo->table("user")->where($where)->inc("age",5);

            $where=[
                ["name","=","John"],
            ];
            //年龄-1
            $res=$mongo->table("user")->where($where)->dec("age");
            //年龄-5
            $res=$mongo->table("user")->where($where)->dec("age",5);
```
5. 查
```
            //查询一条数据  
            $res=$mongo->table("user")->field("name")->where($where)->find();
            //查询多条数据
            $res=$mongo->table("user")->where($where)->select();
            //查询10条数据
            $res=$mongo->table("user")->where($where)->limit(10)->select();
            //查询翻页的数据，例如第二页，每页10条数据
            $res=$mongo->table("user")->where($where)->page(2,10)->select();
            //查询根据性别分组
            $group=[
                'sumAge' => ['$sum' => '$age'], // 聚合字段，计算每个分组中平均年龄
                'avgAge' => ['$avg' => '$age'], // 聚合字段，计算每个分组中平均年龄
            ];
            $having=[
                'avgAge' => ['$gte' => 15], // 传递两个参数给$gte运算符
            ];
            $project=[
                'name' => '$_id',
                'sumAge' => 1,
                'avgAge' => 1,
            ];
            $res=$mongo->table("user")
                ->group("sex",$group) //根据性别分组
                ->having($having) //查平均年龄大于15岁 可不用
                ->project($project) //需要输出的字段，可不用
                ->select();

            //查询数量
            $res=$mongo->table("user")->where($where)->count();
```

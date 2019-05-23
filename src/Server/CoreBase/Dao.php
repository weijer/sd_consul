<?php
/**
 * 基类model
 * Class BaseModel
 * @package app\Models
 */

namespace Server\CoreBase;

use Server\Memory\Pool;

class Dao extends Model
{
    // 当前表
    public $table;
    // 当前模块允许编辑字段
    public $_allEditFields;

    // 数据表字段信息 留空则自动获取
    protected $field = [];

    // 显示属性
    protected $visible = [];

    // 隐藏属性
    protected $hidden = [];

    // 追加属性
    protected $append = [];

    // 数据信息
    protected $data = [];

    // 保存自动完成列表
    protected $auto = [];

    // 新增自动完成列表
    protected $insert = [];

    // 更新自动完成列表
    protected $update = [];

    // 记录改变字段
    protected $change = [];

    // 是否需要自动写入时间戳 如果设置为字符串 则表示时间字段的类型
    protected $autoWriteTimestamp;

    // 创建时间字段
    protected $createTime = 'create_at';

    // 更新时间字段
    protected $updateTime = 'update_at';

    // 时间字段取出后的默认时间格式
    protected $dateFormat = 'Y-m-d H:i:s';

    // 字段类型或者格式转换
    protected $type = [];

    // 验证器
    public $validate;

    // 错误码
    protected $errorCode = -1;

    // 错误信息
    protected $errorMsg = "";

    // Select 查询同时返回total数据
    public $selectWithTotal = false;

    // 开启事务处理
    public $startDBTransaction = false;

    /**
     * @param $context
     */
    public function initialization(&$context)
    {
        parent::initialization($context);
    }

    /**
     * 初始化验证器
     * @param $validate
     * @throws \Server\CoreBase\SwooleException
     */
    public function initValidate($validate)
    {
        $this->validate = Pool::getInstance()->get("\\app\\Validate\\{$validate}");
    }

    /**
     * 还验证器对象
     * @throws \Server\CoreBase\SwooleException
     */
    public function pushValidate()
    {
        Pool::getInstance()->push($this->validate);
    }

    /**
     * 获取错误码
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * 获取错误信息
     */
    public function getError()
    {
        return $this->errorMsg;
    }

    /**
     * 抛出异常
     * @param $code
     * @param $msg
     * @throws \Exception
     */
    protected function throwDaoError($code, $msg)
    {
        $this->errorMsg = $msg;
        $this->errorCode = $code;
        if ($this->startDBTransaction) {
            throw new \Exception($msg, $code);
        } else {
            throw_api_exception($code, $msg);
        }
    }

    /**
     * 排除不允许编辑的字段
     */
    protected function dataExcludeNotAllowFields()
    {
        if (!empty($this->_allEditFields)) {
            $newData = [];
            foreach ($this->data as $key => $value) {
                if (in_array($key, $this->_allEditFields)) {
                    $newData[$key] = $value;
                }
            }
            $this->data = $newData;
        }
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string $name 字符串
     * @param integer $type 转换类型
     * @param bool $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public function parseName($name, $type = 0, $ucfirst = true)
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        } else {
            return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
        }
    }

    /**
     * 数据读取 类型转换
     * @access public
     * @param mixed $value 值
     * @param string|array $type 要转换的类型
     * @return mixed
     */
    protected function readTransform($value, $type)
    {
        if (is_array($type)) {
            list($type, $param) = $type;
        } elseif (strpos($type, ':')) {
            list($type, $param) = explode(':', $type, 2);
        }
        switch ($type) {
            case 'integer':
                $value = (int)$value;
                break;
            case 'float':
                if (empty($param)) {
                    $value = (float)$value;
                } else {
                    $value = (float)number_format($value, $param);
                }
                break;
            case 'boolean':
                $value = (bool)$value;
                break;
            case 'timestamp':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value = date($format, $value);
                }
                break;
            case 'datetime':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value = date($format, strtotime($value));
                }
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
            case 'array':
                $value = is_null($value) ? [] : json_decode($value, true);
                break;
            case 'object':
                $value = empty($value) ? new \stdClass() : json_decode($value);
                break;
            case 'serialize':
                $value = unserialize($value);
                break;
        }
        return $value;
    }

    /**
     * 数据写入 类型转换
     * @access public
     * @param mixed $value 值
     * @param string|array $type 要转换的类型
     * @return mixed
     */
    protected function writeTransform($value, $type)
    {
        if (is_array($type)) {
            list($type, $param) = $type;
        } elseif (strpos($type, ':')) {
            list($type, $param) = explode(':', $type, 2);
        }
        switch ($type) {
            case 'integer':
                $value = (int)$value;
                break;
            case 'float':
                if (empty($param)) {
                    $value = (float)$value;
                } else {
                    $value = (float)number_format($value, $param);
                }
                break;
            case 'boolean':
                $value = (bool)$value;
                break;
            case 'timestamp':
                if (!is_numeric($value)) {
                    $value = strtotime($value);
                }
                break;
            case 'datetime':
                $format = !empty($param) ? $param : $this->dateFormat;
                $value = date($format, is_numeric($value) ? $value : strtotime($value));
                break;
            case 'object':
                if (is_object($value)) {
                    $value = json_encode($value, JSON_FORCE_OBJECT);
                }
                break;
            case 'array':
                $value = (array)$value;
                break;
            case 'json':
                $option = !empty($param) ? (int)$param : JSON_UNESCAPED_UNICODE;
                $value = json_encode($value, $option);
                break;
            case 'serialize':
                $value = serialize($value);
                break;
        }
        return $value;
    }

    /**
     * 自动写入时间戳
     * @access public
     * @param string $name 时间戳字段
     * @return mixed
     */
    protected function autoWriteTimestamp($name)
    {
        if (isset($this->type[$name])) {
            $type = $this->type[$name];
            if (strpos($type, ':')) {
                list($type, $param) = explode(':', $type, 2);
            }
            switch ($type) {
                case 'datetime':
                case 'date':
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value = date($format, $_SERVER['REQUEST_TIME']);
                    break;
                case 'timestamp':
                case 'integer':
                default:
                    $value = $_SERVER['REQUEST_TIME'];
                    break;
            }
        } elseif (is_string($this->autoWriteTimestamp) && in_array(strtolower($this->autoWriteTimestamp), ['datetime', 'date', 'timestamp'])) {
            $value = date($this->dateFormat, $_SERVER['REQUEST_TIME']);
        } else {
            $value = $_SERVER['REQUEST_TIME'];
        }
        return $value;
    }

    /**
     * 验证数据
     * @param string $sceneName
     * @throws \Server\CoreBase\SwooleException
     */
    protected function validateData($sceneName = '')
    {
        $this->validate->scene($sceneName);

        // 验证数据
        if (!$this->validate->check($this->data)) {
            $this->throwDaoError(-41005, $this->validate->getError());
        }

        // 释放验证器资源
        $this->pushValidate();
    }


    /**
     * 获取对象原始数据 如果不存在指定字段返回false
     * @access public
     * @param string $name 字段名 留空获取全部
     * @return mixed
     * @throws \Exception
     */
    public function getData($name = null)
    {
        if (is_null($name)) {
            return $this->data;
        } elseif (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        } else {
            $this->throwDaoError(-41010, 'property not exists:' . $name);
        }
    }

    /**
     * 数据自动完成
     * @access public
     * @param array $fields 要自动更新的字段列表
     * @return void
     */
    protected function autoCompleteData($fields = [])
    {
        $auoFields = array_merge($fields, $this->auto);

        foreach ($auoFields as $field => $value) {
            if (is_integer($field)) {
                $field = $value;
                $value = null;
            }
            if (!in_array($field, $this->change)) {
                $this->setAttr($field, !is_null($value) ? $value : (isset($this->data[$field]) ? $this->data[$field] : $value));
            }
        }

        //  清除change缓存
        $this->change = [];
    }

    /**
     * 修改器 设置数据对象值
     * @access public
     * @param string $name 属性名
     * @param mixed $value 属性值
     * @param array $data 数据
     * @return $this
     */
    public function setAttr($name, $value, $data = [])
    {
        if (is_null($value) && $this->autoWriteTimestamp && in_array($name, [$this->createTime, $this->updateTime])) {
            // 自动写入的时间戳字段
            $value = $this->autoWriteTimestamp($name);
        } else {
            // 检测修改器
            $method = 'set' . $this->parseName($name, 1) . 'Attr';
            if (method_exists($this, $method)) {
                $value = $this->$method($value, array_merge($data, $this->data));
            } elseif (isset($this->type[$name])) {
                // 类型转换
                $value = $this->writeTransform($value, $this->type[$name]);
            }
        }

        // 标记字段更改
        if (isset($this->data[$name]) && is_scalar($this->data[$name]) && is_scalar($value) && 0 !== strcmp($this->data[$name], $value)) {
            $this->change[] = $name;
        } elseif (!isset($this->data[$name]) || $value != $this->data[$name]) {
            $this->change[] = $name;
        }

        // 设置数据对象属性
        $this->data[$name] = $value;
        return $this;
    }

    /**
     * 获取器 获取数据对象的值
     * @param $name
     * @return mixed|null
     * @throws \Exception
     */
    public function getAttr($name)
    {
        try {
            $value = $this->getData($name);
        } catch (\Exception $e) {
            $value = null;
        }

        // 检测属性获取器
        $method = 'get' . $this->parseName($name, 1) . 'Attr';
        if (method_exists($this, $method)) {
            $value = $this->$method($value, $this->data);
        } elseif (isset($this->type[$name])) {
            // 类型转换
            $value = $this->readTransform($value, $this->type[$name]);
        }
        return $value;
    }

    /**
     * 处理查询数据
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function handleQueryData($data)
    {
        $item = [];
        $this->data = !empty($data) ? $data : $this->data;
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $this->data = $val;
                $arr = [];
                foreach ($val as $k => $v) {
                    $arr[$k] = $this->getAttr($k);
                }
                $item[$key] = $arr;
            } else {
                $item[$key] = $this->getAttr($key);
            }
        }

        return !empty($item) ? $item : [];
    }


    /**
     * 处理返回数据
     * @param bool $first
     * @return array|mixed
     * @throws \Exception
     */
    public function handleReturnData($first = true)
    {
        if ($first && is_many_dimension_array($this->data)) {
            $item = [];
            foreach ($this->data as $value) {
                $this->data = $value;
                $item[] = $this->handleReturnData(false);
            }
            return $item;
        } else {
            //过滤属性
            if (!empty($this->visible)) {
                $data = array_intersect_key($this->data, array_flip($this->visible));
            } elseif (!empty($this->hidden)) {
                $data = array_diff_key($this->data, array_flip($this->hidden));
            } else {
                $data = $this->data;
            }

            // 追加属性（必须定义获取器）
            if (!empty($this->append)) {
                foreach ($this->append as $name) {
                    $data[$name] = $this->getAttr($name);
                }
            }
            return !empty($data) ? $data : [];
        }
    }


    /**
     * 组装过滤条件 {"filter":{ "event_log":{"operate":[  "LIKE", "ww" ] }
     * @param $filter
     * @param string $alias
     * @return $this
     */
    public function assemblyFilter($filter, $alias = '')
    {
        if (!empty($filter)) {
            //"IN","NOT IN"的值转为数组
            $requireArrayList = ["IN", "BETWEEN", "NOT BETWEEN", "NOT IN"];
            foreach ($filter as $field => $item) {

                if (is_array($item)) {
                    list($logic, $value) = $item;
                    if (in_array($logic, $requireArrayList) && !is_array($value)) {
                        $value = explode(",", $value);
                    }
                    if (in_array($logic, ["LIKE", "NOT LIKE"])) {
                        $value = "%$value%";
                    }
                } else {
                    $logic = '=';
                    $value = $item;
                }

                if (strpos($field, '->')) {
                    // JSON字段支持
                    list($fieldItem, $name) = explode('->', $field, 2);

                    if (!empty($alias)) {
                        // 设置别名
                        $fieldKey = $alias . '.' . $fieldItem;
                    } else {
                        $fieldKey = $fieldItem;
                    }

                    $field = 'json_extract(' . $fieldKey . ', \'$.' . str_replace('->', '.', $name) . '\')';

                } elseif (!empty($alias)) {
                    // 设置别名
                    $field = $alias . '.' . $field;
                }


                //$this->db->where("`$field`", $value, $logic);

                $this->db->where($field, $value, $logic);
            }
        }
        return $this;
    }

    /**
     * 设置字段
     * @param $fields
     * @return $this
     */
    protected function setFields($fields)
    {
        if (empty($fields)) {
            $setFields = "*";
        } else {
            // 判断是否存储json字段
            $fieldsArr = explode(',', $fields);
            $fieldsList = [];
            foreach ($fieldsArr as $fieldItem) {
                if (strpos($fieldItem, '->')) {
                    // JSON字段支持
                    list($field, $name) = explode('->', $fieldItem, 2);
                    $jsonSQL = 'json_unquote(json_extract(' . $field . ', \'$.' . str_replace('->', '.', $name) . '\'))';

                    if (strpos($fieldItem, '.')) {
                        // 判断是否存在别名
                        list($alias, $fieldName) = explode('.', $field, 2);
                        $jsonSQL .= ' as ' . $alias . '_' . $fieldName;
                    } else {
                        $jsonSQL .= ' as ' . $name;
                    }

                    $fieldsList[] = $jsonSQL;
                } else {
                    $fieldsList[] = $fieldItem;
                }
            }

            $setFields = join(",", $fieldsList);
        }

        $this->db->select($setFields);
        return $this;
    }

    /**
     * 设置分页
     * @param array $pagination
     * @return $this
     */
    public function setPage($pagination = [])
    {
        if (is_array($pagination) && array_key_exists('page_number', $pagination) && $pagination['page_number'] !== 0 && array_key_exists('page_size', $pagination) && $pagination["page_size"] !== 0) {
            $pageSize = intval($pagination['page_size']);
            $pageNumber = intval($pagination["page_number"]);
            $this->db->limit($pageSize, ($pageNumber - 1) * $pageSize);
        } else {
            $this->db->limit(2000);
        }
        return $this;
    }

    /**
     * 设置排序
     * @param array $orders
     * @param string $alias
     * @return $this
     */
    public function setOrder($orders = [], $alias = '')
    {
        if (is_array($orders) && !empty($orders)) {
            if (is_many_dimension_array($orders)) {
                foreach ($orders as list($column, $order)) {
                    $column = !empty($alias) ? $alias . '.' . $column : $column;
                    $this->db->orderBy($column, $order);
                }
            } else {
                list($column, $order) = $orders;
                $column = !empty($alias) ? $alias . '.' . $column : $column;
                $this->db->orderBy($column, $order);
            }
        }
        return $this;
    }

    /**
     * 获取查询数据总数
     * @param array $filter
     * @return int
     * @throws \Server\CoreBase\SwooleException
     * @throws \Throwable
     */
    public function getTotalNumber($filter = [])
    {
        $this->db->from($this->table);
        $this->assemblyFilter($filter);
        $total = $this->db->select('id')->query()->num_rows();
        return $total;
    }

    /**
     * 查找数据
     * @param string $fields 'id,name'
     * @param array $filter ["id" => ["=", 1], ["name" => ["like", "test"]]
     * @param array $pagination ['page_number' => 1, 'page_size'=> 100]
     * @param array $order [['name'=>'asc'],['status'=>'desc']]
     * @return array
     * @throws \Server\CoreBase\SwooleException
     * @throws \Throwable
     */
    public function select($fields = '', $filter = [], $pagination = [], $order = [])
    {
        // 获取总行数
        $total = 0;
        if ($this->selectWithTotal) {
            $total = $this->getTotalNumber($filter);
        }

        // 查询数据
        $this->db->from($this->table);

        $this->assemblyFilter($filter)
            ->setFields($fields)
            ->setPage($pagination)
            ->setOrder($order);

        $rows = $this->db->query()->getResult();

        // 处理查询数据
        $this->data = $this->handleQueryData($rows["result"]);

        if ($this->selectWithTotal) {
            // 查询并返回总行数
            return [
                "total" => $total,
                "rows" => $this->data
            ];
        } else {
            // 仅查询
            return $this->data;
        }
    }

    /**
     * 单条查找
     * @param string $fields 'id,name'
     * @param array $filter ["id" => ["=", 1], ["name" => ["like", "test"]]
     * @return array|null
     * @throws \Server\CoreBase\SwooleException
     * @throws \Throwable
     */
    public function find($fields = '', $filter = [])
    {
        // 查询数据
        $this->db->from($this->table);

        // 设置过滤条件和字段
        $this->assemblyFilter($filter)
            ->setFields($fields);

        $resData = $this->db
            ->limit(1)
            ->query()
            ->row();

        if (!empty($resData)) {
            $this->data = $this->handleQueryData($resData);
            return $this->data;
        }

        return [];
    }

    /**
     * 添加数据
     * @param $data
     * @return mixed
     * @throws \Server\CoreBase\SwooleException
     * @throws \Throwable
     */
    public function add($data)
    {
        $this->data = $data;

        // 设置验证场景
        $this->validateData('Create');

        // 排除非允许编辑字段
        $this->dataExcludeNotAllowFields();

        // 自动完成数据
        $this->autoCompleteData($this->insert);

        // 添加数据
        $resData = $this->db->insert($this->table)
            ->set($this->data)
            ->query();

        if ($resData->affected_rows() > 0) {
            return $resData->getResult();
        } else {
            $this->throwDaoError(-41006, 'create failure.');
        }
    }

    /**
     * 更新指定字段数据
     * @param $data ["id" => ["=", 1], ["name" => ["like", "test"]]
     * @param array $filter
     * @return bool|mixed
     * @throws \Server\CoreBase\SwooleException
     * @throws \Throwable
     */
    public function update($data, array $filter)
    {
        $this->data = $data;

        // 设置验证场景
        $this->validateData('Update');

        // 排除非允许编辑字段
        $this->dataExcludeNotAllowFields();

        // 数据自动完成
        $this->autoCompleteData($this->update);

        // 更新数据
        $this->db->update($this->table);

        // 设置过滤条件
        $this->assemblyFilter($filter);

        $resData = $this->db->set($this->data)
            ->query();

        if ($resData->affected_rows() > 0) {
            return $data;
        } else {
            $this->throwDaoError(-41004, 'update failure.');
        }
    }

    /**
     * 删除数据
     * @param array $filter ["id" => ["=", 1], ["name" => ["like", "test"]]
     * @return bool
     * @throws \Server\CoreBase\SwooleException
     * @throws \Throwable
     */
    public function delete(array $filter)
    {
        $this->db->delete();
        $this->db->from($this->table);

        $this->assemblyFilter($filter);

        $resData = $this->db->query();

        if ($resData->affected_rows() > 0) {
            return true;
        } else {
            $this->throwDaoError(-41003, 'delete failure.');
        }
    }
}

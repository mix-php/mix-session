<?php

namespace Mix\Http\Session;

use Mix\Core\Component\AbstractComponent;
use Mix\Helpers\RandomStringHelper;

/**
 * Class RedisHandler
 * @package Mix\Http\Session
 * @author LIUJIAN <coder.keda@gmail.com>
 */
class RedisHandler extends AbstractComponent implements HandlerInterface
{

    /**
     * @var \Mix\Http\Session\HttpSession
     */
    public $parent;

    /**
     * 连接池
     * @var \Mix\Pool\ConnectionPoolInterface
     */
    public $pool;

    /**
     * 连接
     * @var \Mix\Redis\RedisConnectionInterface
     */
    public $connection;

    /**
     * Key前缀
     * @var string
     */
    public $keyPrefix = 'SESSION:';

    /**
     * SessionID
     * @var string
     */
    protected $_sessionId = '';

    /**
     * SessionKey
     * @var string
     */
    protected $_key = '';

    /**
     * 初始化事件
     */
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 从连接池获取连接
        if (isset($this->pool)) {
            $this->connection = $this->pool->getConnection();
        }
    }

    /**
     * 针对每个请求执行初始化
     */
    public function beforeInitialize()
    {
        // 加载 SessionId
        if (!$this->loadSessionId()) {
            // 创建 session_id
            $this->createSessionId();
        }
        // 延长 session 有效期
        $this->connection->expire($this->_key, $this->parent->maxLifetime);
    }

    /**
     * 加载 SessionId
     * @return bool
     */
    public function loadSessionId()
    {
        $sessionId = \Mix::$app->request->cookie($this->parent->name);
        if (is_null($sessionId)) {
            return false;
        }
        $this->_sessionId = $sessionId;
        $this->_key       = $this->keyPrefix . $this->_sessionId;
        return true;
    }

    /**
     * 创建 SessionId
     * @return bool
     */
    public function createSessionId()
    {
        do {
            $this->_sessionId = RandomStringHelper::randomAlphanumeric($this->parent->sessionIdLength);
            $this->_key       = $this->keyPrefix . $this->_sessionId;
        } while ($this->connection->exists($this->_key));
        return true;
    }

    /**
     * 获取 SessionId
     * @return string
     */
    public function getSessionId()
    {
        return $this->_sessionId;
    }

    /**
     * 设置 cookie
     * @return bool
     */
    public function setCookie()
    {
        return \Mix::$app->response->setCookie(
            $this->parent->name,
            $this->getSessionId(),
            $this->parent->cookieExpires,
            $this->parent->cookiePath,
            $this->parent->cookieDomain,
            $this->parent->cookieSecure,
            $this->parent->cookieHttpOnly
        );
    }

    /**
     * 赋值
     * @param $key
     * @param $value
     * @return bool
     */
    public function set($key, $value)
    {
        $success = $this->connection->hmset($this->_key, [$key => serialize($value)]);
        $this->connection->expire($this->_key, $this->parent->maxLifetime);
        if ($success) {
            $this->setCookie();
        }
        return $success ? true : false;
    }

    /**
     * 取值
     * @param null $key
     * @return mixed
     */
    public function get($key = null)
    {
        if (is_null($key)) {
            $result = $this->connection->hgetall($this->_key);
            foreach ($result as $key => $item) {
                $result[$key] = unserialize($item);
            }
            return $result ?: [];
        }
        $value = $this->connection->hget($this->_key, $key);
        return $value === false ? null : unserialize($value);
    }

    /**
     * 删除
     * @param $key
     * @return bool
     */
    public function delete($key)
    {
        $success = $this->connection->hdel($this->_key, $key);
        return $success ? true : false;
    }

    /**
     * 清除session
     * @return bool
     */
    public function clear()
    {
        $success = $this->connection->del($this->_key);
        return $success ? true : false;
    }

    /**
     * 判断是否存在
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        $exist = $this->connection->hexists($this->_key, $key);
        return $exist ? true : false;
    }

}

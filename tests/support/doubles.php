<?php

/**
 * Test doubles for the MyAdmin framework services Plugin calls into.
 *
 * Plugin::loadProcessing() registers lifecycle closures on a \ServiceHandler
 * and those closures reach straight out to global framework services
 * (\MyAdmin\App::history(), get_module_db(), \TFSmarty, \MyAdmin\Mail, ...).
 * These doubles record what they were asked to do, so a test can *invoke* a
 * lifecycle closure and assert its observable effects instead of grepping
 * src/Plugin.php for the spelling of a call.
 */

namespace Detain\MyAdminDomains\Tests\Support {
    /**
     * Records \MyAdmin\History::add() calls made through \MyAdmin\App::history().
     */
    class HistorySpy
    {
        /**
         * Every recorded call, in order.
         *
         * @var array<int,array<string,mixed>>
         */
        public $entries = [];

        /**
         * Mirrors \MyAdmin\History::add()'s signature.
         *
         * @param string $section history section
         * @param string $type history type
         * @param string $new new value
         * @param string $old old value
         * @param bool|int $custid customer id
         * @param bool|string $extra optional extra info
         * @return int the fake history id
         */
        public function add($section, $type, $new, $old = '', $custid = false, $extra = false)
        {
            $this->entries[] = [
                'section' => $section,
                'type' => $type,
                'new' => $new,
                'old' => $old,
                'custid' => $custid,
                'extra' => $extra,
            ];
            return count($this->entries);
        }
    }

    /**
     * Records myadmin_log() calls.
     */
    class LogSpy
    {
        /**
         * @var array<int,array<string,mixed>>
         */
        private static $calls = [];

        /**
         * @param string $module
         * @param string $level
         * @param string $message
         * @return void
         */
        public static function record($module, $level, $message)
        {
            self::$calls[] = [
                'module' => $module,
                'level' => $level,
                'message' => $message,
            ];
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        public static function calls()
        {
            return self::$calls;
        }

        /**
         * @return void
         */
        public static function reset()
        {
            self::$calls = [];
        }
    }

    /**
     * Stands in for \ServiceHandler: captures the lifecycle closures the
     * plugin registers so a test can run one of them.
     */
    class ServiceHandlerSpy
    {
        /** @var string|null module name passed to setModule() */
        public $module;

        /** @var array<int,string>|false statuses passed to setActivationStatuses() */
        public $activationStatuses = false;

        /** @var bool whether register() was called */
        public $registered = false;

        /** @var array<string,callable> the registered lifecycle closures */
        public $callbacks = [];

        /** @var array<string,mixed> the service row handed to the closures */
        private $serviceInfo = [];

        /** @var bool mirrors \ServiceHandler::$success, which defaults to true */
        private $success = true;

        /**
         * @param string $module
         * @return $this
         */
        public function setModule($module)
        {
            $this->module = $module;
            return $this;
        }

        /**
         * @param array<int,string>|false $statuses
         * @return $this
         */
        public function setActivationStatuses($statuses = false)
        {
            $this->activationStatuses = $statuses;
            return $this;
        }

        /**
         * @param callable $callable
         * @return $this
         */
        public function setEnable($callable)
        {
            $this->callbacks['enable'] = $callable;
            return $this;
        }

        /**
         * @param callable $callable
         * @return $this
         */
        public function setDisable($callable)
        {
            $this->callbacks['disable'] = $callable;
            return $this;
        }

        /**
         * @param callable $callable
         * @return $this
         */
        public function setReactivate($callable)
        {
            $this->callbacks['reactivate'] = $callable;
            return $this;
        }

        /**
         * @param callable $callable
         * @return $this
         */
        public function setTerminate($callable)
        {
            $this->callbacks['terminate'] = $callable;
            return $this;
        }

        /**
         * @param callable $callable
         * @return $this
         */
        public function setVerify($callable)
        {
            $this->callbacks['verify'] = $callable;
            return $this;
        }

        /**
         * @return $this
         */
        public function register()
        {
            $this->registered = true;
            return $this;
        }

        /**
         * @param array<string,mixed> $info
         * @return $this
         */
        public function setServiceInfo($info)
        {
            $this->serviceInfo = $info;
            return $this;
        }

        /**
         * @return array<string,mixed>
         */
        public function getServiceInfo()
        {
            return $this->serviceInfo;
        }

        /**
         * @param bool $success
         * @return $this
         */
        public function setSuccess($success)
        {
            $this->success = (bool)$success;
            return $this;
        }

        /**
         * @return bool
         */
        public function getSuccess()
        {
            return $this->success;
        }

        /**
         * Runs one registered lifecycle closure, exactly as the real
         * ServiceHandler does: passing itself in as the argument.
         *
         * @param string $name enable|disable|reactivate|terminate|verify
         * @return mixed
         */
        public function run($name)
        {
            if (!isset($this->callbacks[$name])) {
                throw new \RuntimeException('No ' . $name . ' callback was registered');
            }
            return call_user_func($this->callbacks[$name], $this);
        }
    }

    /**
     * Minimal stand-in for the module database handle.
     *
     * The recorded queries are held statically on purpose: the real
     * get_module_db() hands callers a *clone* of $GLOBALS['<module>_dbh'],
     * so per-instance state would never make it back to the test.
     */
    class DbSpy
    {
        /**
         * Every query() call, in order.
         *
         * @var array<int,string>
         */
        public static $queries = [];

        /** @var int value returned by affectedRows() */
        public static $affectedRows = 1;

        /**
         * @param string $query
         * @param int|string $line
         * @param string $file
         * @return void
         */
        public function query($query = '', $line = '', $file = '')
        {
            self::$queries[] = $query;
        }

        /**
         * @return int
         */
        public function affectedRows()
        {
            return self::$affectedRows;
        }

        /**
         * @return void
         */
        public static function reset()
        {
            self::$queries = [];
            self::$affectedRows = 1;
        }
    }
}

namespace MyAdmin {
    if (!class_exists(App::class, false)) {
        /**
         * Stand-in for the real \MyAdmin\App facade. Only history() is
         * reached by this plugin, and it hands back a spy the tests own.
         */
        class App
        {
            /** @var \Detain\MyAdminDomains\Tests\Support\HistorySpy|null */
            private static $history;

            /**
             * @return \Detain\MyAdminDomains\Tests\Support\HistorySpy
             */
            public static function history()
            {
                if (self::$history === null) {
                    self::$history = new \Detain\MyAdminDomains\Tests\Support\HistorySpy();
                }
                return self::$history;
            }

            /**
             * Installs a fresh spy and returns it. Call from setUp().
             *
             * @return \Detain\MyAdminDomains\Tests\Support\HistorySpy
             */
            public static function resetHistory()
            {
                self::$history = new \Detain\MyAdminDomains\Tests\Support\HistorySpy();
                return self::$history;
            }
        }
    }

    if (!class_exists(Mail::class, false)) {
        /**
         * Stand-in for \MyAdmin\Mail that records admin notifications.
         */
        class Mail
        {
            /** @var array<int,array<string,mixed>> */
            public static $sent = [];

            /**
             * Mirrors \MyAdmin\Mail::adminMail().
             *
             * @param string $subject
             * @param string $email
             * @param bool|string $who
             * @param string $template
             * @param bool|string $cc
             * @param bool|string $bcc
             * @return bool
             */
            public function adminMail($subject, $email, $who = false, $template = '', $cc = false, $bcc = false)
            {
                self::$sent[] = [
                    'subject' => $subject,
                    'email' => $email,
                    'who' => $who,
                    'template' => $template,
                ];
                return true;
            }

            /**
             * @return void
             */
            public static function reset()
            {
                self::$sent = [];
            }
        }
    }
}

namespace {
    if (!class_exists('TFSmarty', false)) {
        /**
         * Stand-in for \TFSmarty that records assignments and returns a
         * recognisable stub body for fetch().
         */
        class TFSmarty
        {
            /** @var array<string,mixed> */
            public $assigned = [];

            /**
             * @param string|array<string,mixed> $key
             * @param mixed $value
             * @return void
             */
            public function assign($key, $value = null)
            {
                if (is_array($key)) {
                    $this->assigned = array_merge($this->assigned, $key);
                    return;
                }
                $this->assigned[$key] = $value;
            }

            /**
             * @param string $template
             * @return string
             */
            public function fetch($template)
            {
                return 'rendered:' . $template;
            }
        }
    }
}

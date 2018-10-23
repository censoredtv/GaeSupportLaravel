<?php

namespace A1comms\GaeSupportLaravel\Integration\TaskQueue;

use Google\Cloud\Tasks\V2beta2\Task;
use Google\Cloud\Tasks\V2beta2\AppEngineHttpRequest;

if (GAE_LEGACY) {
    class_alias('google\appengine\api\taskqueue\PushTask', 'A1comms\GaeSupportLaravel\Integration\TaskQueue\PushTask');
} else {
    class PushTask
    {
        private $task;
        private $pushTask;

        public function __construct($url_path, $query_data = [], $options = []) {
            $this->pushTask = new AppEngineHttpRequest();

            $this->pushTask->setRelativeUrl($url_path);
            $this->pushTask->setPayload($query_data);

            if (in_array('method', $options)) {
                $this->pushTask->setMethod($options['method']);
            }

            $this->task = new Task();
            $this->task->setAppEngineHttpRequest($this->pushTask);

            if (in_array('delay_seconds', $options)) {
                $secondsString = sprintf('+%s seconds', $options['delay_seconds']);
                $futureTime = date(\DateTime::RFC3339, strtotime($secondsString));
                $this->task->setScheduleTime($futureTime);
            }
        }

        public function getTask() {
            return $this->task;
        }

        public function add($queue_name = 'default') {
            $queue = new PushQueue($queue_name);
            return $queue->addTasks([$this])[0];
        }
    }
}
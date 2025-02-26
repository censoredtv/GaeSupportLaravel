<?php

namespace A1comms\GaeSupportLaravel\Integration\TaskQueue;

use DateTime;
use DateInterval;
use Google\Protobuf;
use Google\Cloud\Tasks\V2\Task;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\AppEngineRouting;
use Google\Cloud\Tasks\V2\AppEngineHttpRequest;
use Illuminate\Support\Facades\Log;

class PushTask
{
    private $task;
    private $pushTask;

    public function __construct($url_path, $query_data = [], $options = [])
    {
        $this->pushTask = new AppEngineHttpRequest();

        $this->pushTask->setRelativeUri($url_path);
        $this->pushTask->setBody(http_build_query($query_data));
        $this->pushTask->setHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        if (!empty($options['target'])) {
            $routing = new AppEngineRouting();

            if (!empty($options['target']['service'])) {
                $routing->setService($options['target']['service']);
            }

            if (!empty($options['target']['version'])) {
                $routing->setVersion($options['target']['version']);
            }

            $this->pushTask->setAppEngineRouting($routing);
        } elseif (is_gae_development()) {
            /**
             * Support our development environment,
             * which runs working copies on live
             * App Engine containers via rsync to /tmp.
             *
             * Our chosen naming convention is versions
             * starting with "dev-", so if we spot that
             * in the version name, send tasks back
             * to that specific version.
             */
            Log::info('Detected development environment, routing to ' . gae_service() . ':' . gae_version());

            $routing = (new AppEngineRouting())
                ->setService(gae_service())
                ->setVersion(gae_version());

            $this->pushTask->setAppEngineRouting($routing);
        } elseif (gae_service() != "default") {
            $routing = (new AppEngineRouting())
                ->setService(gae_service());

            $this->pushTask->setAppEngineRouting($routing);
        }

        if (!empty($options['method'])) {
            $this->pushTask->setHttpMethod(
                HttpMethod::value($options['method'])
            );
        }

        $this->task = new Task();
        $this->task->setAppEngineHttpRequest($this->pushTask);

        if (!empty($options['delay_seconds'])) {
            $secondsInterval = new DateInterval('PT'.$options['delay_seconds'].'S');
            $futureTime = (new DateTime())->add($secondsInterval);
            $timestamp = new Protobuf\Timestamp();
            $timestamp->fromDateTime($futureTime);
            $this->task->setScheduleTime($timestamp);
        }
    }

    public function getTask()
    {
        return $this->task;
    }

    public function add($queue_name = 'default')
    {
        $queue = new PushQueue($queue_name);
        return $queue->addTasks([$this])[0];
    }

    public static function parseTaskName(Task $task)
    {
        // In Format: `projects/PROJECT_ID/locations/LOCATION_ID/queues/QUEUE_ID/tasks/TASK_ID`
        $taskName = $task->getName();

        $taskDetails = explode('/', $taskName);

        return [
            'project_id' => $taskDetails[1],
            'location_id' => $taskDetails[3],
            'queue_id' => $taskDetails[5],
            'task_id' => $taskDetails[7],
        ];
    }
}

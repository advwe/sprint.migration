<?php

namespace Sprint\Migration;

use Sprint\Migration\Exceptions\RestartException;

class SchemaManager
{
    /** @var VersionConfig */
    private $versionConfig = null;

    protected $params = array();

    private $progress = 0;

    public function __construct($params = array()) {
        $this->versionConfig = new VersionConfig('cfg');

        $this->params = $params;
    }

    public function outDescriptions() {
        $schemas = $this->getSchemas();
        $schemas = array_keys($schemas);

        foreach ($schemas as $name) {
            $this->createSchema($name)->outDescription();
        }
    }

    protected function getSchemas() {
        return $this->getVersionConfig()->getVal('version_schemas');
    }

    public function export() {
        $schemas = $this->getSchemas();
        $schemas = array_keys($schemas);

        foreach ($schemas as $name) {
            $this->exportSchema($name);
        }


    }

    public function import() {
        $schemas = $this->getSchemas();
        $schemas = array_keys($schemas);

        if (!isset($this->params['schema'])) {
            $this->params['schema'] = 0;
        }

        if (isset($schemas[$this->params['schema']])) {

            $name = $schemas[$this->params['schema']];
            $this->importSchema($name);

            $this->params['schema']++;
            $this->restart();
        }

        unset($this->params['schema']);
    }

    public function getProgress() {
        return $this->progress;
    }

    protected function setProgress($index, $cnt) {
        if ($cnt > 0) {
            $this->progress = round($index / $cnt * 100);
        } else {
            $this->progress = 0;
        }
    }

    protected function exportSchema($name) {
        $schema = $this->createSchema($name);
        $schema->export();
    }

    protected function importSchema($name) {
        $schema = $this->createSchema($name);

        if (!isset($this->params['index'])) {
            $this->outSuccess('%s (import) start', $name);

            $this->params['index'] = 0;
            $schema->import();
            $this->saveQueue($schema);
        }

        $queue = $this->loadQueue($schema);
        $queueCount = count($queue);

        if (isset($queue[$this->params['index']])) {
            $item = $queue[$this->params['index']];
            $this->executeQueue($schema, $item);

            $this->setProgress(
                $this->params['index'],
                $queueCount - 1
            );

            $this->params['index']++;
            $this->restart();
        }

        $this->removeQueue($schema);
        unset($this->params['index']);

        $this->out('%s (import) success', $name);
    }

    protected function getVersionConfig() {
        return $this->versionConfig;
    }

    /** @return AbstractSchema */
    protected function createSchema($name) {
        $schemas = $this->getSchemas();
        $class = $schemas[$name];

        return new $class($this->getVersionConfig(), $name);
    }

    protected function out($msg, $var1 = null, $var2 = null) {
        $args = func_get_args();
        call_user_func_array(array('Sprint\Migration\Out', 'out'), $args);
    }

    protected function outError($msg, $var1 = null, $var2 = null) {
        $args = func_get_args();
        call_user_func_array(array('Sprint\Migration\Out', 'outErrorText'), $args);
    }

    protected function outSuccess($msg, $var1 = null, $var2 = null) {
        $args = func_get_args();
        call_user_func_array(array('Sprint\Migration\Out', 'outSuccessText'), $args);
    }

    protected function executeQueue(AbstractSchema $schema, $item) {
        if (method_exists($schema, $item[0])) {
            call_user_func_array(array($schema, $item[0]), $item[1]);
        } else {
            $this->outError('method %s not found', $item[0]);
        }
    }

    protected function removeQueue(AbstractSchema $schema) {
        $file = $this->getQueueFile($schema);
        if (is_file($file)) {
            unlink($file);
        }
    }

    protected function loadQueue(AbstractSchema $schema) {
        $file = $this->getQueueFile($schema);
        if (is_file($file)) {
            $items = include $file;
            if (
                $items &&
                isset($items['items']) &&
                is_array($items['items'])
            ) {
                return $items['items'];
            }
        }

        return array();
    }


    protected function saveQueue(AbstractSchema $schema) {
        $data = $schema->getQueue();
        $file = $this->getQueueFile($schema);

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, BX_DIR_PERMISSIONS, true);
        }

        file_put_contents($file, '<?php return ' . var_export(array('items' => $data), 1) . ';');
    }

    protected function getQueueFile(AbstractSchema $schema) {
        $name = $schema->getName();

        $name = 'compiled__' . strtolower($name);
        return $this->getVersionConfig()->getVal('migration_dir') . '/schema/' . $name . '.php';
    }

    protected function restart() {
        Throw new RestartException('restart');
    }

    public function getRestartParams() {
        return $this->params;
    }
}
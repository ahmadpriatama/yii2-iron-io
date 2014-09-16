<?php
/**
 * ErrorHandler.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2014 spacedealer GmbH
 */


namespace spacedealer\iron\console;

/**
 * Class ErrorHandler
 *
 * @package spacedealer\iron\console
 */
class ErrorHandler extends \yii\console\ErrorHandler
{
    /**
     * @event Event an event that is triggered before the exception is rendered via [[renderException()]].
     */
    const EVENT_RENDER = 'render';

    /**
     * @event Event an event that is triggered before the exception is logged via [[logException()]].
     */
    const EVENT_LOG = 'log';

    /**
     * Renders an exception using ansi format for console output.
     *
     * @param \Exception $exception the exception to be rendered.
     */
    protected function renderException($exception)
    {
        $this->trigger(self::EVENT_RENDER);
        parent::renderException($exception);
    }

    /**
     * Logs the given exception
     *
     * @param \Exception $exception the exception to be logged
     */
    protected function logException($exception)
    {
        $this->trigger(self::EVENT_LOG);
        parent::logException($exception);
    }
} 
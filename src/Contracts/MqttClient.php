<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\ProtocolViolationException;
use PhpMqtt\Client\Exceptions\RepositoryException;

/**
 * An interface for the MQTT client.
 *
 * @package PhpMqtt\Client\Contracts
 */
interface MqttClient
{
    /**
     * Connect to the MQTT broker using the given settings.
     * If no custom settings are passed, the client will use the default settings.
     * See {@see ConnectionSettings} for more details about the defaults.
     *
     * @param ConnectionSettings|null $settings
     * @param bool                    $sendCleanSessionFlag
     * @return void
     * @throws ConfigurationInvalidException
     * @throws ConnectingToBrokerFailedException
     */
    public function connect(
        ConnectionSettings $settings = null,
        bool $sendCleanSessionFlag = false
    ): void;

    /**
     * Returns an indication, whether the client is supposed to be connected already or not.
     *
     * Note: the result of this method should be used carefully, since we can only detect a
     * closed socket once we try to send or receive data. Therefore, this method only gives
     * an indication whether the client is in a connected state or not.
     *
     * This information may be useful in applications where multiple parts use the client.
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Publishes the given message on the given topic. If the additional quality of service
     * and retention flags are set, the message will be published using these settings.
     *
     * @param string $topic
     * @param string $message
     * @param int    $qualityOfService
     * @param bool   $retain
     * @return void
     * @throws DataTransferException
     * @throws RepositoryException
     */
    public function publish(string $topic, string $message, int $qualityOfService = 0, bool $retain = false): void;

    /**
     * Subscribe to the given topic with the given quality of service.
     *
     * The subscription callback is passed the topic as first and the message as second
     * parameter. A third parameter indicates whether the received message has been sent
     * because it was retained by the broker.
     *
     * Example:
     * ```php
     * $mqtt->subscribe(
     *     '/foo/bar/+',
     *     function (string $topic, string $message, bool $retained) use ($logger) {
     *         $logger->info("Received {retained} message on topic [{topic}]: {message}", [
     *             'topic' => $topic,
     *             'message' => $message,
     *             'retained' => $retained ? 'retained' : 'live'
     *         ]);
     *     }
     * );
     * ```
     *
     * @param string   $topic
     * @param callable $callback
     * @param int      $qualityOfService
     * @return void
     * @throws DataTransferException
     * @throws RepositoryException
     */
    public function subscribe(string $topic, callable $callback, int $qualityOfService = 0): void;

    /**
     * Unsubscribe from the given topic.
     *
     * @param string $topic
     * @return void
     * @throws DataTransferException
     */
    public function unsubscribe(string $topic): void;

    /**
     * Sends a disconnect and closes the socket.
     *
     * @return void
     * @throws DataTransferException
     */
    public function close(): void;

    /**
     * Sets the interrupted signal. Doing so instructs the client to exit the loop, if it is
     * actually looping.
     *
     * Sending multiple interrupt signals has no effect, unless the client exits the loop,
     * which resets the signal for another loop.
     *
     * @return void
     */
    public function interrupt(): void;

    /**
     * Runs an event loop that handles messages from the server and calls the registered
     * callbacks for published messages.
     *
     * If the second parameter is provided, the loop will exit as soon as all
     * queues are empty. This means there may be no open subscriptions,
     * no pending messages as well as acknowledgments and no pending unsubscribe requests.
     *
     * The third parameter will, if set, lead to a forceful exit after the specified
     * amount of seconds, but only if the second parameter is set to true. This basically
     * means that if we wait for all pending messages to be acknowledged, we only wait
     * a maximum of $queueWaitLimit seconds until we give up. We do not exit after the
     * given amount of time if there are open topic subscriptions though.
     *
     * @param bool     $allowSleep
     * @param bool     $exitWhenQueuesEmpty
     * @param int|null $queueWaitLimit
     * @return void
     * @throws DataTransferException
     * @throws ProtocolViolationException
     */
    public function loop(bool $allowSleep = true, bool $exitWhenQueuesEmpty = false, int $queueWaitLimit = null): void;

    /**
     * Returns the host used by the client to connect to.
     *
     * @return string
     */
    public function getHost(): string;

    /**
     * Returns the port used by the client to connect to.
     *
     * @return int
     */
    public function getPort(): int;

    /**
     * Returns the identifier used by the client.
     *
     * @return string
     */
    public function getClientId(): string;

    /**
     * Returns the total number of received bytes, across reconnects.
     *
     * @return int
     */
    public function getReceivedBytes(): int;

    /**
     * Returns the total number of sent bytes, across reconnects.
     *
     * @return int
     */
    public function getSentBytes(): int;

    /**
     * Registers a loop event handler which is called each iteration of the loop.
     * This event handler can be used for example to interrupt the loop under
     * certain conditions.
     *
     * The loop event handler is passed the MQTT client instance as first and
     * the elapsed time which the loop is already running for as second
     * parameter. The elapsed time is a float containing seconds.
     *
     * Example:
     * ```php
     * $mqtt->registerLoopEventHandler(function (
     *     MqttClient $mqtt,
     *     float $elapsedTime
     * ) use ($logger) {
     *     $logger->info("Running for [{$elapsedTime}] seconds already.");
     * });
     * ```
     *
     * Multiple event handlers can be registered at the same time.
     *
     * @param \Closure $callback
     * @return MqttClient
     */
    public function registerLoopEventHandler(\Closure $callback): MqttClient;

    /**
     * Unregisters a loop event handler which prevents it from being called
     * in the future.
     *
     * This does not affect other registered event handlers. It is possible
     * to unregister all registered event handlers by passing null as callback.
     *
     * @param \Closure|null $callback
     * @return MqttClient
     */
    public function unregisterLoopEventHandler(\Closure $callback = null): MqttClient;

    /**
     * Registers a loop event handler which is called when a message is published.
     *
     * The loop event handler is passed the MQTT client as first, the topic as
     * second and the message as third parameter. As fourth parameter, the
     * message identifier will be passed. The QoS level as well as the retained
     * flag will also be passed as fifth and sixth parameters.
     *
     * Example:
     * ```php
     * $mqtt->registerPublishEventHandler(function (
     *     MqttClient $mqtt,
     *     string $topic,
     *     string $message,
     *     int $messageId,
     *     int $qualityOfService,
     *     bool $retain
     * ) use ($logger) {
     *     $logger->info("Received message on topic [{$topic}]: {$message}");
     * });
     * ```
     *
     * Multiple event handlers can be registered at the same time.
     *
     * @param \Closure $callback
     * @return MqttClient
     */
    public function registerPublishEventHandler(\Closure $callback): MqttClient;

    /**
     * Unregisters a publish event handler which prevents it from being called
     * in the future.
     *
     * This does not affect other registered event handlers. It is possible
     * to unregister all registered event handlers by passing null as callback.
     *
     * @param \Closure|null $callback
     * @return MqttClient
     */
    public function unregisterPublishEventHandler(\Closure $callback = null): MqttClient;
}
